<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout;

use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;

class OrderLine extends AbstractLineItem
{
    /**
     * @throws \Exception
     */
    public function __construct(
        OrderItemInterface $orderItem,
        TaxRateResolverInterface $taxRateResolver,
        string $type = 'physical',
        string $locale = 'en_US',
    ) {
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
        $variantName = $orderItem->getVariantName() ?? $orderName;
        if ($variantName === null || $orderName === null) {
            throw new \Exception('Product and variant must have a name');
        }

        // resolve tax rate
        $taxRate = $taxRateResolver->resolve($variant);
        $taxRateAmount = $taxRate !== null ? (int) ($taxRate->getAmount() * 100 * 100) : 0;

        $itemUnitPrice = $orderItem->getUnitPrice();

        $orderItemTotal = $orderItem->getTotal();
        $quantity = $orderItem->getQuantity();
        $unitPrice = (int) ($orderItemTotal / $quantity);

        $this->type = $type;
        $this->reference = $variantCode;
        $this->name = $orderName . ' - ' . $variantName;
        $this->quantity = $orderItem->getQuantity();
        $this->quantityUnit = 'pcs';
        $this->unitPrice = $unitPrice;
        $this->taxRate = $taxRateAmount;
        $this->totalAmount = $orderItemTotal;
        $unitDiscount = $itemUnitPrice - $orderItem->getFullDiscountedUnitPrice();
        $this->totalDiscountAmount = $this->quantity * $unitDiscount;
        $this->totalTaxAmount = $orderItem->getTaxTotal();
    }

    public function getLineItem(): LineItemInterface
    {
        return $this;
    }
}
