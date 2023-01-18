<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout;

abstract class AbstractLineItem implements LineItemInterface
{
    protected string $type;

    protected string $reference;

    protected string $name;

    protected int $quantity;

    protected string $quantityUnit;

    protected int $unitPrice;

    protected int $taxRate;

    protected int $totalAmount;

    protected int $totalDiscountAmount;

    protected int $totalTaxAmount;

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'reference' => $this->reference,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'quantity_unit' => $this->quantityUnit,
            'unit_price' => $this->unitPrice,
            'tax_rate' => $this->taxRate,
            'total_amount' => $this->totalAmount,
            'total_discount_amount' => $this->totalDiscountAmount,
            'total_tax_amount' => $this->totalTaxAmount,
        ];
    }
}
