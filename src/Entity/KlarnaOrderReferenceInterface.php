<?php

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Entity;

use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;

interface KlarnaOrderReferenceInterface extends TimestampableInterface, ResourceInterface
{
    public function setReference(string $reference): void;
    public function getReference(): ?string;

}
