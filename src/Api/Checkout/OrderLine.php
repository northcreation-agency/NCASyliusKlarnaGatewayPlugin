<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout;

use Doctrine\Persistence\ObjectRepository;
use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Addressing\Model\ZoneMemberInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;

class OrderLine extends AbstractLineItem
{
    /**
     * @throws \Exception
     */
    public function __construct(
        OrderItemInterface $orderItem,
        TaxRateResolverInterface $taxRateResolver,
        private ObjectRepository $zoneMemberRepository,
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
        $zones = $this->getTaxZonesFromBilling($orderItem);
        $zoneCriteria = ['zone' => $zones];
        $taxRate = $taxRateResolver->resolve($variant, $zoneCriteria);
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

    /**
     * @throws \Exception
     *
     * @param OrderItemInterface $item
     * @return ZoneInterface[]
     */
    protected function getTaxZonesFromBilling(OrderItemInterface $item): array
    {
        $order = $item->getOrder();
        if (!$order instanceof OrderInterface) {
            throw new \Exception('Order item must have an order');
        }
        $billingAddress = $order->getBillingAddress();
        if ($billingAddress === null) {
            throw new \Exception('Order must have a billing address');
        }

        $countryCode = $billingAddress->getCountryCode();


        return $this->getZonesForCountryCode($countryCode);
    }

    /**
     * @throws \Exception
     *
     * @return ZoneInterface[]
     */
    protected function getZonesForCountryCode(string $countryCode): array
    {
        $zoneMembers = $this->zoneMemberRepository->findBy(['code' => $countryCode]);
        $zones = [];
        /** @var ZoneMemberInterface $zoneMember */
        foreach ($zoneMembers as $zoneMember) {
            $zone = $zoneMember->getBelongsTo();
            if ($zone !== null) {
                $zones[] = $zone;
            }
        }

        if (count($zones) === 0) {
            throw new \Exception('Could not find zone for country code ' . $countryCode);
        }

        return $zones;
    }
}
