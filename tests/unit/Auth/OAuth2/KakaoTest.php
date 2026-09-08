<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\OAuth2;

use Appwrite\Auth\OAuth2\Exception;
use Appwrite\Auth\OAuth2\Kakao;
use Appwrite\Extend\Exception as AppwriteException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class KakaoTest extends TestCase
{
    public function testLoginURL(): void
    {
        $kakao = new Kakao('rest-api-key', 'client-secret', 'https://example.com/callback', ['success' => 'https://example.com']);

        $url = \parse_url($kakao->getLoginURL());
        \parse_str($url['query'], $query);

        $this->assertSame('kauth.kakao.com', $url['host']);
        $this->assertSame('/oauth/authorize', $url['path']);
        $this->assertSame('rest-api-key', $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('https://example.com/callback', $query['redirect_uri']);
        $this->assertSame(['success' => 'https://example.com'], \json_decode($query['state'], true));

        // Kakao rejects the authorization (KOE205) for any consent item that
        // is not activated on the app, so none is requested by default
        $this->assertArrayNotHasKey('scope', $query);
    }

    public function testLoginURLSeparatesScopesWithCommas(): void
    {
        $kakao = new Kakao('rest-api-key', 'client-secret', 'https://example.com/callback', [], ['profile_nickname', 'account_email']);

        \parse_str(\parse_url($kakao->getLoginURL(), PHP_URL_QUERY), $query);

        $this->assertSame('profile_nickname,account_email', $query['scope']);
    }

    public function testAccessToken(): void
    {
        $kakao = $this->createKakao();

        $kakao
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://kauth.kakao.com/oauth/token',
                ['Content-Type: application/x-www-form-urlencoded;charset=utf-8'],
                $this->callback(function (string $payload): bool {
                    \parse_str($payload, $params);

                    $this->assertSame([
                        'grant_type' => 'authorization_code',
                        'client_id' => 'rest-api-key',
                        'client_secret' => 'client-secret',
                        'redirect_uri' => 'https://example.com/callback',
                        'code' => 'authorization-code',
                    ], $params);

                    return true;
                }),
            )
            ->willReturn(\json_encode([
                'token_type' => 'bearer',
                'access_token' => 'access-token',
                'expires_in' => 43199,
                'refresh_token' => 'refresh-token',
                'refresh_token_expires_in' => 5184000,
                'scope' => 'account_email profile',
            ], JSON_THROW_ON_ERROR));

        $this->assertSame('access-token', $kakao->getAccessToken('authorization-code'));
        $this->assertSame('refresh-token', $kakao->getRefreshToken('authorization-code'));
        $this->assertSame(43199, $kakao->getAccessTokenExpiry('authorization-code'));
    }

    public function testBadClientCredentials(): void
    {
        $kakao = $this->createKakao();

        $kakao
            ->expects($this->once())
            ->method('request')
            ->willThrowException(new Exception(\json_encode([
                'error' => 'invalid_client',
                'error_description' => 'Bad client credentials',
                'error_code' => 'KOE010',
            ], JSON_THROW_ON_ERROR), 401));

        try {
            $kakao->getAccessToken('authorization-code');
            $this->fail('Expected the Kakao OAuth2 provider error to be thrown.');
        } catch (Exception $exception) {
            $this->assertSame(AppwriteException::USER_OAUTH2_UNAUTHORIZED, $exception->getType());
            $this->assertSame('invalid_client', $exception->getError());
            $this->assertSame('Bad client credentials', $exception->getErrorDescription());
        }
    }

    public function testRefreshTokensKeepsExistingRefreshToken(): void
    {
        $kakao = $this->createKakao();

        $kakao
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                'https://kauth.kakao.com/oauth/token',
                $this->anything(),
                $this->callback(function (string $payload): bool {
                    \parse_str($payload, $params);

                    $this->assertSame([
                        'grant_type' => 'refresh_token',
                        'client_id' => 'rest-api-key',
                        'client_secret' => 'client-secret',
                        'refresh_token' => 'existing-refresh-token',
                    ], $params);

                    return true;
                }),
            )
            // Kakao only reissues a refresh token when the current one is
            // about to expire
            ->willReturn(\json_encode([
                'token_type' => 'bearer',
                'access_token' => 'refreshed-access-token',
                'expires_in' => 43199,
            ], JSON_THROW_ON_ERROR));

        $tokens = $kakao->refreshTokens('existing-refresh-token');

        $this->assertSame('refreshed-access-token', $tokens['access_token']);
        $this->assertSame('existing-refresh-token', $tokens['refresh_token']);
    }

    public function testUserClaims(): void
    {
        $kakao = $this->createKakao($this->user());

        $this->assertSame('123456789', $kakao->getUserID('access-token'));
        $this->assertSame('sample@sample.com', $kakao->getUserEmail('access-token'));
        $this->assertSame('Ryan', $kakao->getUserName('access-token'));
        $this->assertSame('http://kakao.example/profile.jpg', $kakao->getUserPhoto('access-token'));
        $this->assertTrue($kakao->isEmailVerified('access-token'));
    }

    /**
     * @param array<string, mixed> $account
     */
    #[DataProvider('unverifiedEmails')]
    public function testEmailIsNotVerified(array $account): void
    {
        $kakao = $this->createKakao($this->user($account));

        $this->assertFalse($kakao->isEmailVerified('access-token'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unverifiedEmails(): iterable
    {
        yield 'not verified' => [['is_email_verified' => false]];
        yield 'no longer valid' => [['is_email_valid' => false]];
    }

    public function testMissingConsent(): void
    {
        // Nothing but the id is returned when no consent item was granted
        $kakao = $this->createKakao(['id' => 123456789, 'connected_at' => '2021-09-23T06:08:31Z']);

        $this->assertSame('123456789', $kakao->getUserID('access-token'));
        $this->assertSame('', $kakao->getUserEmail('access-token'));
        $this->assertSame('', $kakao->getUserName('access-token'));
        $this->assertSame('', $kakao->getUserPhoto('access-token'));
        $this->assertFalse($kakao->isEmailVerified('access-token'));
    }

    /**
     * @param array<string, mixed> $account
     *
     * @return array<string, mixed>
     */
    private function user(array $account = []): array
    {
        return [
            'id' => 123456789,
            'connected_at' => '2021-09-23T06:08:31Z',
            'kakao_account' => \array_merge([
                'profile_nickname_needs_agreement' => false,
                'profile_image_needs_agreement' => false,
                'profile' => [
                    'nickname' => 'Ryan',
                    'thumbnail_image_url' => 'http://kakao.example/thumbnail.jpg',
                    'profile_image_url' => 'http://kakao.example/profile.jpg',
                    'is_default_image' => false,
                ],
                'email_needs_agreement' => false,
                'is_email_valid' => true,
                'is_email_verified' => true,
                'email' => 'sample@sample.com',
            ], $account),
        ];
    }

    /**
     * @param array<string, mixed>|null $user
     */
    private function createKakao(?array $user = null): Kakao&MockObject
    {
        $kakao = $this->getMockBuilder(Kakao::class)
            ->setConstructorArgs(['rest-api-key', 'client-secret', 'https://example.com/callback'])
            ->onlyMethods(['request'])
            ->getMock();

        if ($user !== null) {
            $kakao
                // The account is fetched once and reused across the getters
                ->expects($this->once())
                ->method('request')
                ->with(
                    'GET',
                    'https://kapi.kakao.com/v2/user/me',
                    ['Authorization: Bearer access-token'],
                )
                ->willReturn(\json_encode($user, JSON_THROW_ON_ERROR));
        }

        return $kakao;
    }
}
