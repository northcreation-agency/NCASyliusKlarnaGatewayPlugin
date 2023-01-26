<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Entity;

use Sylius\Component\Addressing\Model\Address;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Resource\Model\TimestampableTrait;

class KlarnaOrderReference implements KlarnaOrderReferenceInterface
{
    use TimestampableTrait;

    /** @var mixed */
    protected $id;

    protected ?string $reference;

    protected OrderInterface $order;

    public function __construct(OrderInterface $order)
    {
        $this->reference = null;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setReference(string $reference): void
    {
        $this->reference = $reference;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }


}
