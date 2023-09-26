<?php

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Entity;

interface ChannelDomainAwareInterface
{
    public function getDomainHost(): ?string;
}
