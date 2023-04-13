<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class KlarnaRequestStructure
{
    public const CHECKOUT = 'checkout';

    public const REFUND = 'refund';

    public function __construct(
        private OrderInterface $order,
        private TaxRateResolverInterface $taxRateResolver,
        private OrderProcessorInterface $shippingChargesProcessor,
        private CalculatorInterface $taxCalculator,
        private ParameterBagInterface $parameterBag,
        private ?MerchantData $merchantData = null,
        private string $type = self::CHECKOUT,
    ) {
    }

    /**
     * @throws \Exception
     */
    public function toArray(): array
    {
        if ($this->type === self::REFUND) {
            return $this->refundArray();
        }

        return $this->checkoutArray();
    }

    protected function checkoutArray(): array
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
            'merchant_urls' => $this->merchantData?->toArray() ?? [],
            'billing_address' => $billingAddressData->toArray(),
            'merchant_reference1' => $referenceNumber,
        ];

        if ($shippingAddress !== null) {
            $shippingAddressData = new AddressData($shippingAddress);

            $requestStructure['shipping_address'] = $shippingAddressData->toArray();
        }

        return $requestStructure;
    }

    protected function refundArray(): array
    {
        $orderLines = $this->getOrderLinesForOrder($this->order);
        $orderLinesArray = [];
        foreach ($orderLines as $orderLine) {
            $orderLinesArray[] = $orderLine->toArray();
        }

        /** @psalm-suppress UndefinedClass (UnitEnum is supported as of PHP 8.1)
         * @var bool $includeShipping
         */
        $includeShipping = $this->parameterBag->get(
            'north_creation_agency_sylius_klarna_gateway.refund.include_shipping',
        );

        if ($includeShipping === true) {
            $shipmentLines = $this->getShipmentLinesForOrder($this->order);
            foreach ($shipmentLines as $shipmentLine) {
                $orderLinesArray[] = $shipmentLine->toArray();
            }
        }

        return [
            'description' => 'Initialized refund from web shop',
            'refunded_amount' => $this->sumOfLineItems($orderLinesArray),
            'reference' => $this->order->getNumber(),
            'order_lines' => $orderLinesArray,
        ];
    }

    protected function sumOfLineItems(array $orderLines): int
    {
        return array_reduce($orderLines, fn (int $sum, array $line) => $sum + (int) $line['total_amount'], 0);
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

    public function getType(): string
    {
        return $this->type;
    }
}
