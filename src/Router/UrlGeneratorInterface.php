<?php

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Router;

interface UrlGeneratorInterface
{
    public function generateAbsoluteURL(string $url, array $replacementMap = []): string;
}
