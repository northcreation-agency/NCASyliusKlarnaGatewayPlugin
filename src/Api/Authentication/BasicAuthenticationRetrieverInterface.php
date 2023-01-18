<?php

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication;

use Sylius\Component\Core\Model\PaymentMethodInterface;

interface BasicAuthenticationRetrieverInterface
{
    public function getBasicAuthentication(PaymentMethodInterface $paymentMethod): string;

}
