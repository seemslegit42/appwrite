<?php

declare(strict_types=1);

namespace Tests\Unit\GraphQL;

use Appwrite\GraphQL\Resolvers;
use Appwrite\Utopia\Request;
use Appwrite\Utopia\Response;
use Appwrite\Utopia\Response\Model\Any;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Utopia\Database\Document;
use Utopia\DI\Container;
use Utopia\Http\Adapter\FPM\Server;
use Utopia\Http\Http;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ResolversTest extends TestCase
{
    #[DataProvider('payloads')]
    public function testApiPreservesPayload(array $payload, array $expected): void
    {
        \putenv('_APP_GRAPHQL_MAX_DEPTH=2');

        $container = new Container();
        $http = new Http(new Server($container), 'UTC');
        $container->set('utopia:graphql', static fn () => $http);
        $container->set('request', static fn () => new Request(new SwooleRequest()));
        $container->set('response', static fn () => new Response(new SwooleResponse()));
        Response::setModel(new Any());

        $route = Http::get('/graphql-resolver-test')
            ->hook(false)
            ->inject('response')
            ->action(static function (Response $response) use ($payload): void {
                $response->dynamic(new Document($payload), Response::MODEL_ANY);
            });
        $resolver = Resolvers::api($http, $route, Http::REQUEST_METHOD_GET);
        $actual = null;
        $error = null;

        \Swoole\Coroutine\run(static function () use ($resolver, &$actual, &$error): void {
            $resolver(null, [], null, null)->then(
                static function ($value) use (&$actual): void {
                    $actual = $value;
                },
                static function ($value) use (&$error): void {
                    $error = $value;
                }
            );
        });

        $this->assertNull($error);
        $this->assertSame($expected, $actual);
    }

    public static function payloads(): \Iterator
    {
        yield 'nested data beyond the escaping limit' => [
            ['users' => [['targets' => [['providerType' => 'email']]]]],
            ['users' => [['targets' => [['providerType' => 'email']]]]],
        ];
        yield 'array-valued system keys' => [
            ['$permissions' => ['read("any")'], '$nested' => ['$id' => 'child']],
            ['_permissions' => ['read("any")'], '_nested' => ['_id' => 'child']],
        ];
    }
}
