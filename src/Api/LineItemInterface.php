<?php

namespace AndersBjorkland\SyliusKlarnaGatewayPlugin\Api;

interface LineItemInterface
{
    public function toArray(): array;
    public function getLineItem(): self;
}
