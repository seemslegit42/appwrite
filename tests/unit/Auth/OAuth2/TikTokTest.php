<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\OAuth2;

use Appwrite\Auth\OAuth2\Exception;
use Appwrite\Auth\OAuth2\TikTok;
use Appwrite\Extend\Exception as AppwriteException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class TikTokTest extends TestCase
{
    public function testLoginURL(): void
    {
        $tiktok = new TikTok('client-key', 'client-secret', 'https://example.com/callback', ['success' => 'https://example.com']);

        $url = \parse_url($tiktok->getLoginURL());
        \parse_str($url['query'], $query);

        $this->assertSame('www.tiktok.com', $url['host']);
        $this->assertSame('/v2/auth/authorize/', $url['path']);

        // TikTok names the client identifier client_key, not client_id
        $this->assertSame('client-key', $query['client_key']);
        $this->assertArrayNotHasKey('client_id', $query);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('https://example.com/callback', $query['redirect_uri']);
        $this->assertSame('user.info.basic', $query['scope']);
        $this->assertSame(['success' => 'https://example.com'], \json_decode($query['state'], true));
    }

    public function testLoginURLSeparatesScopesWithCommas(): void
    {
        $tiktok = new TikTok('client-key', 'client-secret', 'https://example.com/callback', [], ['user.info.profile']);

        \parse_str(\parse_url($tiktok->getLoginURL(), PHP_URL_QUERY), $query);

        $this->assertSame('user.info.basic,user.info.profile', $query['scope']);
    }

    public function testAccessToken(): void
    {
        $tiktok = $this->createTikTok();

        $tiktok
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://open.tiktokapis.com/v2/oauth/token/',
                ['Content-Type: application/x-www-form-urlencoded', 'Cache-Control: no-cache'],
                $this->callback(function (string $payload): bool {
                    \parse_str($payload, $params);

                    $this->assertSame([
                        'client_key' => 'client-key',
                        'client_secret' => 'client-secret',
                        'code' => 'authorization-code',
                        'grant_type' => 'authorization_code',
                        'redirect_uri' => 'https://example.com/callback',
                    ], $params);

                    return true;
                }),
            )
            ->willReturn(\json_encode([
                'access_token' => 'act.example',
                'expires_in' => 86400,
                'open_id' => 'open-id',
                'refresh_token' => 'rft.example',
                'scope' => 'user.info.basic',
                'token_type' => 'Bearer',
            ], JSON_THROW_ON_ERROR));

        $this->assertSame('act.example', $tiktok->getAccessToken('authorization-code'));
        // Tokens are cached, so a second read must not hit the provider again
        $this->assertSame('rft.example', $tiktok->getRefreshToken('authorization-code'));
    }

    public function testProviderError(): void
    {
        $tiktok = $this->createTikTok();

        // TikTok answers a rejected token request with a 200 and an error body
        $tiktok
            ->expects($this->once())
            ->method('request')
            ->willReturn(\json_encode([
                'error' => 'invalid_request',
                'error_description' => 'Redirect_uri is not matched with the uri when requesting code.',
                'log_id' => '202206221854370101130062072500FFA2',
            ], JSON_THROW_ON_ERROR));

        try {
            $tiktok->getAccessToken('authorization-code');
            $this->fail('Expected the TikTok OAuth2 provider error to be thrown.');
        } catch (Exception $exception) {
            $this->assertSame(AppwriteException::USER_OAUTH2_BAD_REQUEST, $exception->getType());
            $this->assertSame('invalid_request', $exception->getError());
            $this->assertSame('Redirect_uri is not matched with the uri when requesting code.', $exception->getErrorDescription());
        }
    }

    public function testRefreshTokensKeepsExistingRefreshToken(): void
    {
        $tiktok = $this->createTikTok();

        $tiktok
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://open.tiktokapis.com/v2/oauth/token/',
                $this->anything(),
                $this->callback(function (string $payload): bool {
                    \parse_str($payload, $params);

                    $this->assertSame([
                        'client_key' => 'client-key',
                        'client_secret' => 'client-secret',
                        'grant_type' => 'refresh_token',
                        'refresh_token' => 'rft.existing',
                    ], $params);

                    return true;
                }),
            )
            ->willReturn(\json_encode([
                'access_token' => 'act.refreshed',
                'expires_in' => 86400,
            ], JSON_THROW_ON_ERROR));

        $tokens = $tiktok->refreshTokens('rft.existing');

        $this->assertSame('act.refreshed', $tokens['access_token']);
        $this->assertSame('rft.existing', $tokens['refresh_token']);
    }

    public function testUserClaims(): void
    {
        $tiktok = $this->createTikTok();

        $tiktok
            ->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://open.tiktokapis.com/v2/user/info/?fields=' . \rawurlencode('open_id,union_id,display_name,avatar_url'),
                ['Authorization: Bearer act.example'],
            )
            ->willReturn(\json_encode([
                'data' => [
                    'user' => [
                        'open_id' => '723f24d7-e717-40f8-a2b6-cb8464cd23b4',
                        'union_id' => 'c9c60f44-a68e-4f5d-84dd-ce22faeb0ba1',
                        'display_name' => 'Ryan',
                        'avatar_url' => 'https://p19-sign.tiktokcdn-us.com/avatar.jpeg',
                    ],
                ],
                'error' => ['code' => 'ok', 'message' => '', 'log_id' => '20220829194722CBE87ED59D524E727021'],
            ], JSON_THROW_ON_ERROR));

        // The account is identified by open_id, which is scoped to the app
        $this->assertSame('723f24d7-e717-40f8-a2b6-cb8464cd23b4', $tiktok->getUserID('act.example'));
        $this->assertSame('Ryan', $tiktok->getUserName('act.example'));
        $this->assertSame('https://p19-sign.tiktokcdn-us.com/avatar.jpeg', $tiktok->getUserPhoto('act.example'));

        // Login Kit exposes no email, so nothing can be verified
        $this->assertSame('', $tiktok->getUserEmail('act.example'));
        $this->assertFalse($tiktok->isEmailVerified('act.example'));
    }

    public function testUserError(): void
    {
        $tiktok = $this->createTikTok();

        $tiktok
            ->expects($this->once())
            ->method('request')
            ->willReturn(\json_encode([
                'data' => [],
                'error' => ['code' => 'scope_not_authorized', 'message' => 'The access token is not authorized.', 'log_id' => 'log-id'],
            ], JSON_THROW_ON_ERROR));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('TikTok did not return valid user information.');

        $tiktok->getUserID('act.example');
    }

    private function createTikTok(): TikTok&MockObject
    {
        return $this->getMockBuilder(TikTok::class)
            ->setConstructorArgs(['client-key', 'client-secret', 'https://example.com/callback'])
            ->onlyMethods(['request'])
            ->getMock();
    }
}
