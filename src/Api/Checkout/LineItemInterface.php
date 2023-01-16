<?php

declare(strict_types=1);

namespace AndersBjorkland\SyliusKlarnaGatewayPlugin\Api\Checkout;

interface LineItemInterface
{
    public function toArray(): array;

    public function getLineItem(): self;
}
