<?php

declare(strict_types=1);

namespace AndersBjorkland\SyliusKlarnaGatewayPlugin\Api;

use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;

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
        TaxRateResolverInterface $taxRateResolver,
        string $type = 'physical',
        string $locale = 'en_US',
    ){
        $variant = $orderItem->getVariant();
        if ($variant === null) {
            throw new \Exception('Order item must have a variant');
        }
        $variant->setCurrentLocale($locale);

        $variantCode = $variant->getCode();
        if ($variantCode === null) {
            throw new \Exception('Variant must have a code');
        }


        $orderName = $orderItem->getProductName();
        $variantName = $orderItem->getVariantName();
        if ($variantName === null || $orderName === null) {
            throw new \Exception('Product and variant must have a name');
        }

        // resolve tax rate
        $taxRateFloat = $taxRateResolver->resolve($variant)?->getAmount() ?? 0.0;

        $this->type = $type;
        $this->reference = $variantCode;
        $this->name = $orderName . ' - ' . $variantName;
        $this->quantity = $orderItem->getQuantity();
        $this->quantityUnit = 'pcs';
        $this->unitPrice = $orderItem->getUnitPrice();
        $this->taxRate = (int)($taxRateFloat * 100 * 100);
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
