<?php

namespace Appwrite\Utopia\Response\Model;

use Appwrite\Utopia\Response;

class OAuth2Kakao extends OAuth2Base
{
    public array $conditions = [
        '$id' => 'kakao',
    ];

    /**
     * @return string
     */
    public function getProviderLabel(): string
    {
        return 'Kakao';
    }

    /**
     * @return string
     */
    public function getClientIdLabel(): string
    {
        return 'REST API key';
    }

    /**
     * @return string
     */
    public function getClientIdExample(): string
    {
        return '2b8e0f000000000000000000000c4a17';
    }

    /**
     * @return string
     */
    public function getClientSecretExample(): string
    {
        return 'dK7Yp2000000000000000000000RqXwV';
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'OAuth2Kakao';
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return Response::MODEL_OAUTH2_KAKAO;
    }
}
