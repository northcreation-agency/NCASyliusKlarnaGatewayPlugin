<?php

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Router;

use Symfony\Component\Routing\RouterInterface;

class UrlGenerator
{
    public function __construct(
        private RouterInterface $router
    ){}

    public function generateAbsoluteURL(string $url, array $replacementMap = []): string
    {
        $context = $this->router->getContext();

        /**
         * @var string|int $key
         * @var string $value
         */
        foreach ($replacementMap as $key => $value) {
            if (str_contains($url, '{' . $key . '}')) {
                $url = str_replace('{' . $key . '}', $value, $url);
            }
        }

        if (str_starts_with($url, 'http')) {
            return $url;
        }

        $scheme = $context->getScheme();
        $host = $context->getHost();
        $https = $context->getHttpsPort();
        $http = $context->getHttpPort();

        $port = $scheme === 'https' ? $https : $http;

        if ($port !== 80 && $port !== 443) {
            $port = ':' . $port;
        } else {
            $port = '';
        }

        $url = str_starts_with($url, '/') ? $url : '/' . $url;

        return "$scheme://$host$port$url";
    }
}
