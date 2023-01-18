<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CredentialsConverter
{
    public static function toBase64(
        #[\SensitiveParameter] string $userName,
        #[\SensitiveParameter] string $password
    ): string
    {
        return base64_encode($userName . ':' . $password);
    }
}
