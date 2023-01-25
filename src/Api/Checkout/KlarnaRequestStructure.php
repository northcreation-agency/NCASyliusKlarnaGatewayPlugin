<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;

class KlarnaRequestStructure
{
    public function __construct(
        private OrderInterface $order,
        private MerchantData $merchantData,
        private TaxRateResolverInterface $taxRateResolver,
        private OrderProcessorInterface $shippingChargesProcessor,
        private CalculatorInterface $taxCalculator
    ) {
    }

    /**
     * @throws \Exception
     */
    public function toArray(): array
    {
        $orderLines = $this->getOrderLinesForOrder($this->order);
        $orderLinesArray = [];
        foreach ($orderLines as $orderLine) {
            $orderLinesArray[] = $orderLine->toArray();
        }

        $shipmentLines = $this->getShipmentLinesForOrder($this->order);
        foreach ($shipmentLines as $shipmentLine) {
            $orderLinesArray[] = $shipmentLine->toArray();
        }

        $locale = $this->order->getLocaleCode();
        assert(is_string($locale));
        $locale = str_replace('_', '-', $locale);

        return [
            'purchase_country' => $this->order->getBillingAddress()?->getCountryCode() ?? '',
            'purchase_currency' => $this->order->getCurrencyCode(),
            'locale' => $locale,
            'order_amount' => $this->order->getTotal(),
            'order_tax_amount' => $this->getTaxTotal(array_merge($orderLines, $shipmentLines)),
            'order_lines' => $orderLinesArray,
            'merchant_urls' => $this->merchantData->toArray(),
        ];
    }

    /**
     * @param array|AbstractLineItem[] $orderLines
     * @return int
     */
    protected function getTaxTotal(array $orderLines): int
    {
        $taxTotal = 0;
        foreach ($orderLines as $orderLine) {
            $taxTotal += $orderLine->getTotalTaxAmount();
        }

        return $taxTotal;
    }

    /**
     * Assumes Order items to be of type 'physical'
     *
     * @throws \Exception
     *
     * @return OrderLine[]
     */
    protected function getOrderLinesForOrder(OrderInterface $order): array
    {
        $orderLines = [];
        $currentLocale = $order->getLocaleCode();
        assert($currentLocale !== null);

        foreach ($order->getItems() as $item) {
            $orderLines[] = new OrderLine(
                $item,
                $this->taxRateResolver,
                $this->taxCalculator,
                'physical',
                $currentLocale
            );
        }

        return $orderLines;
    }

    /**
     * @throws \Exception
     *
     * @return ShipmentLine[]
     */
    protected function getShipmentLinesForOrder(OrderInterface $order): array
    {
        $shipmentLines = [];

        foreach ($order->getShipments() as $shipment) {
            $shipmentLines[] = new ShipmentLine($shipment, $this->shippingChargesProcessor);
        }

        return $shipmentLines;
    }
}
