<?php

namespace AndersBjorkland\SyliusKlarnaGatewayPlugin\Api\Checkout;

interface LineItemInterface
{
    public function toArray(): array;
    public function getLineItem(): self;
}
