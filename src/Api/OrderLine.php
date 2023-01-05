<?php

declare(strict_types=1);

namespace AndersBjorkland\SyliusKlarnaGatewayPlugin\Api;

use Sylius\Component\Core\Model\OrderItemInterface;

class OrderLine
{
    private string $type;
    private string $reference;
    private string $name;
    private int $quantity;
    private string $quantityUnit;
    private int $unitPrice;
    private int $taxRate;
    private int $totalAmount;
    private int $totalDiscountAmount;
    private int $totalTaxAmount;

    /**
     * @throws \Exception
     */
    public function __construct(
        OrderItemInterface $orderItem,
        string $type = 'physical',
    ){
        $variant = $orderItem->getVariant();
        if ($variant === null) {
            throw new \Exception('Order item must have a variant');
        }

        $variantCode = $variant->getCode();
        if ($variantCode === null) {
            throw new \Exception('Variant must have a code');
        }

        $variantName = $variant->getName();
        if ($variantName === null) {
            throw new \Exception('Variant must have a name');
        }

        $this->type = $type;
        $this->reference = $variantCode;
        $this->name = $variantName;
        $this->quantity = $orderItem->getQuantity();
        $this->quantityUnit = 'pcs';
        $this->unitPrice = $orderItem->getUnitPrice();
        $this->taxRate = $orderItem->getTaxTotal();
        $this->totalAmount = $orderItem->getTotal();
        $this->totalDiscountAmount = $orderItem->getTotal() - $orderItem->getDiscountedUnitPrice();
        $this->totalTaxAmount = $orderItem->getTaxTotal();
    }

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
