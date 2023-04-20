<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout;

use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class KlarnaRequestStructure
{
    public const CHECKOUT = 'checkout';

    public const REFUND = 'refund';

    public const CAPTURE = 'capture';

    public function __construct(
        private OrderInterface $order,
        private TaxRateResolverInterface $taxRateResolver,
        private OrderProcessorInterface $shippingChargesProcessor,
        private ParameterBagInterface $parameterBag,
        private OrderNumberAssignerInterface $orderNumberAssigner,
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

        return match ($this->type) {
            self::REFUND => $this->refundArray(),
            self::CAPTURE => $this->captureArray(),
            default => $this->checkoutArray()
        };
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

        if ($this->order->getNumber() === null) {
            $this->orderNumberAssigner->assignNumber($this->order);
        }

        $referenceNumber = $this->order->getNumber();

        $requestStructure = [
            'purchase_country' => $this->order->getBillingAddress()?->getCountryCode() ?? '',
            'purchase_currency' => $this->order->getCurrencyCode(),
            'locale' => $locale,
            'order_amount' => array_reduce(
                $orderLinesArray,
                fn (int $sum, array $orderLine) => $sum + (int) $orderLine['total_amount'],
                0,
            ),
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

    private function captureArray(): array
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

        $shipmentsArray = [];
        $shipments = $this->order->getShipments();
        foreach ($shipments as $shipment) {
            $shippingMethod = $shipment->getMethod();
            assert($shippingMethod !== null);

            $shipmentsArray[] = [
                'shipping_company' => $shippingMethod->getName(),
                'tracking_number' => $shipment->getTracking() ?? '',
            ];
        }

        return [
            'captured_amount' => $this->sumOfLineItems($orderLinesArray),
            'description' => 'Shipped from webshop',
            'shipping_info' => $shipmentsArray,
        ];
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
