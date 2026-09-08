<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Modules\Proxy;

use Appwrite\Extend\Exception;
use Appwrite\Platform\Modules\Proxy\Action;
use PHPUnit\Framework\TestCase;

final class ActionTest extends TestCase
{
    public function testEmptyFunctionsDomainSegmentDoesNotRestrictEveryDomain(): void
    {
        // A trailing comma leaves an empty segment. An empty hostname matches
        // every domain, so the restriction it builds would reject anything that
        // is not exactly two labels deep.
        $this->withFunctionsDomain('functions.localhost,', function (): void {
            (new ProxyActionTestAction())->exposeValidateDomainRestrictions('app.mycompany.com', ['hostnames' => []]);
        });
    }

    private function withFunctionsDomain(string $value, callable $callback): void
    {
        $original = \getenv('_APP_DOMAIN_FUNCTIONS');

        \putenv('_APP_DOMAIN_FUNCTIONS=' . $value);

        try {
            $callback();
            $this->assertTrue(true);
        } catch (Exception $e) {
            $this->fail('Domain was rejected: ' . $e->getMessage());
        } finally {
            if ($original === false) {
                \putenv('_APP_DOMAIN_FUNCTIONS');
            } else {
                \putenv('_APP_DOMAIN_FUNCTIONS=' . $original);
            }
        }
    }
}

final class ProxyActionTestAction extends Action
{
    public function exposeValidateDomainRestrictions(string $domain, array $platform): void
    {
        $this->validateDomainRestrictions($domain, $platform);
    }
}
