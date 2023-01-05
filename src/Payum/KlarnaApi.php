<?php

declare(strict_types=1);

namespace AndersBjorkland\SyliusKlarnaGatewayPlugin\Payum;

final class KlarnaApi
{
    private string $apiKey;

    public function __construct(#[\SensitiveParameter] string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }
}
