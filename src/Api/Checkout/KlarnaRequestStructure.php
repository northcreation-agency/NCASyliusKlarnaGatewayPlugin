<?php

declare(strict_types=1);

namespace AndersBjorkland\SyliusKlarnaGatewayPlugin\Api\Checkout;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;

class KlarnaRequestStructure
{
    public function __construct(
        private OrderInterface $order,
        private MerchantData $merchantData,
        private TaxRateResolverInterface $taxRateResolver,
        private OrderProcessorInterface $shippingChargesProcessor,
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

        return [
            'purchase_country' => $this->order->getBillingAddress()?->getCountryCode() ?? '',
            'purchase_currency' => $this->order->getCurrencyCode(),
            'locale' => $this->order->getLocaleCode(),
            'order_amount' => $this->order->getTotal(),
            'order_tax_amount' => $this->order->getTaxTotal(),
            'order_lines' => $orderLinesArray,
            'merchant_urls' => $this->merchantData->toArray(),
        ];
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
            $orderLines[] = new OrderLine($item, $this->taxRateResolver, 'physical', $currentLocale);
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
