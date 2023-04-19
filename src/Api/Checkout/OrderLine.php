<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout;

use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;

class OrderLine extends AbstractLineItem
{
    /**
     * @throws \Exception
     */
    public function __construct(
        OrderItemInterface $orderItem,
        TaxRateResolverInterface $taxRateResolver,
        CalculatorInterface $taxCalculator,
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
        if ($taxRate !== null) {
            $unitTax = $taxCalculator->calculate($orderItem->getFullDiscountedUnitPrice(), $taxRate);
            $taxIsIncluded = $taxRate->isIncludedInPrice();
            $totalTax = $unitTax * $orderItem->getQuantity();
            $unitPrice = $taxIsIncluded ? $itemUnitPrice : $itemUnitPrice + $unitTax;
            $orderItemTotal = $taxIsIncluded ? $orderItemTotal : $orderItemTotal + $totalTax;
        } else {
            $totalTax = 0;
            $unitPrice = $itemUnitPrice;
        }

        $this->type = $type;
        $this->reference = $variantCode;
        $this->name = $orderName . ' - ' . $variantName;
        $this->quantity = $orderItem->getQuantity();
        $this->quantityUnit = 'pcs';
        $this->unitPrice = (int) $unitPrice;
        $this->taxRate = $taxRateAmount;
        $this->totalAmount = (int) $orderItemTotal;
        $unitDiscount = $itemUnitPrice - $orderItem->getFullDiscountedUnitPrice();
        $this->totalDiscountAmount = $this->quantity * $unitDiscount;
        $this->totalTaxAmount = (int) ($totalTax);
    }

    public function getLineItem(): LineItemInterface
    {
        return $this;
    }
}
