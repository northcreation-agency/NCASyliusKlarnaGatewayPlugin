<?php

declare(strict_types=1);

namespace AndersBjorkland\SyliusKlarnaGatewayPlugin\Api\Authentication;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CredentialsConverter
{
    public function __construct(
        private ParameterBagInterface $parameterBag
    ){}

    /**
     * @throws \Exception
     */
    public function toBase64(): string
    {
        $userName = $this->parameterBag->get('klarna_gateway.username');
        $password = $this->parameterBag->get('klarna_gateway.password');

        if (!is_string($userName) || !is_string($password)) {
            throw new \Exception('Klarna username or password is missing');
        }

        return base64_encode($userName . ':' . $password);
    }
}
