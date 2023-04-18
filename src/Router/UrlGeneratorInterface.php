<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Router;

interface UrlGeneratorInterface
{
    public function generateAbsoluteURL(string $url, array $replacementMap = []): string;
}
