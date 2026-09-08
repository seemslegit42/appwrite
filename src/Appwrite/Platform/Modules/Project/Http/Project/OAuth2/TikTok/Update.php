<?php

namespace Appwrite\Platform\Modules\Project\Http\Project\OAuth2\TikTok;

use Appwrite\Auth\OAuth2\TikTok;
use Appwrite\Platform\Modules\Project\Http\Project\OAuth2\Base;
use Appwrite\Utopia\Response;

class Update extends Base
{
    public static function getProviderId(): string
    {
        return 'tiktok';
    }

    public static function getProviderClass(): string
    {
        return TikTok::class;
    }

    public static function getProviderLabel(): string
    {
        return 'TikTok';
    }

    public static function getProviderSDKMethod(): string
    {
        return 'updateOAuth2TikTok';
    }

    public static function getResponseModel(): string
    {
        return Response::MODEL_OAUTH2_TIKTOK;
    }

    public static function getClientIdName(): string
    {
        return 'Client Key';
    }

    public static function getClientIdExample(): string
    {
        return 'aw6vzq00000000ki';
    }

    public static function getClientSecretName(): string
    {
        return 'Client Secret';
    }

    public static function getClientSecretExample(): string
    {
        return '2f4b17a0000000000000000000000000000d9c5e';
    }
}
