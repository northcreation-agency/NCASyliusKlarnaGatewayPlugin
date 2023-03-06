<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout;

use Sylius\Component\Core\Model\Order;
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
        private CalculatorInterface $taxCalculator,
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

        $shippingAddress = $this->order->getShippingAddress();

        $billingAddress = $this->order->getBillingAddress();
        if ($billingAddress === null) {
            throw new \Exception('Could not find a billing address!');
        }

        $customer = $this->order->getCustomer();
        $billingAddressData = new AddressData($billingAddress, $customer);

        /** @var int|string $orderId */
        $orderId = $this->order->getId();
        if (is_int($orderId)) {
            $orderId = '' . $orderId;
        }
        $referenceNumber = $this->order->getNumber() ?? str_pad(
            $orderId,
            9,
            '0',
            \STR_PAD_LEFT,
        );

        $requestStructure = [
            'purchase_country' => $this->order->getBillingAddress()?->getCountryCode() ?? '',
            'purchase_currency' => $this->order->getCurrencyCode(),
            'locale' => $locale,
            'order_amount' => $this->order->getTotal(),
            'order_tax_amount' => $this->getTaxTotal(array_merge($orderLines, $shipmentLines)),
            'order_lines' => $orderLinesArray,
            'merchant_urls' => $this->merchantData->toArray(),
            'billing_address' => $billingAddressData->toArray(),
            'merchant_reference1' => $referenceNumber,
        ];

        if ($shippingAddress !== null) {
            $shippingAddressData = new AddressData($shippingAddress);

            $requestStructure['shipping_address'] = $shippingAddressData->toArray();
        }

        return $requestStructure;
    }

    /**
     * @param AbstractLineItem[] $orderLines
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
                $currentLocale,
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
