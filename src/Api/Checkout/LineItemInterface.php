<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout;

interface LineItemInterface
{
    public function toArray(): array;

    public function getLineItem(): self;
}
