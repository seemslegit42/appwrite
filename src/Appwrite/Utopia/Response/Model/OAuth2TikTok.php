<?php

namespace Appwrite\Utopia\Response\Model;

use Appwrite\Utopia\Response;

class OAuth2TikTok extends OAuth2Base
{
    public array $conditions = [
        '$id' => 'tiktok',
    ];

    /**
     * @return string
     */
    public function getProviderLabel(): string
    {
        return 'TikTok';
    }

    /**
     * @return string
     */
    public function getClientIdLabel(): string
    {
        return 'client key';
    }

    /**
     * @return string
     */
    public function getClientIdExample(): string
    {
        return 'aw6vzq00000000ki';
    }

    /**
     * @return string
     */
    public function getClientSecretExample(): string
    {
        return '2f4b17a0000000000000000000000000000d9c5e';
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'OAuth2TikTok';
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return Response::MODEL_OAUTH2_TIKTOK;
    }
}
