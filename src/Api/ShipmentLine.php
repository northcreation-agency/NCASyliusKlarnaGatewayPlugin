<?php

declare(strict_types=1);

namespace AndersBjorkland\SyliusKlarnaGatewayPlugin\Api;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Taxation\Model\TaxableInterface;

class ShipmentLine extends AbstractLineItem
{
    /**
     * @throws \Exception
     */
    public function __construct(
        ShipmentInterface $shipment,
    ){
        $shippingMethod = $shipment->getMethod();
        if ($shippingMethod === null) {
            throw new \Exception('Shipment method not set');
        }

        $name = $shippingMethod->getName();
        $code = $shippingMethod->getCode();
        if ($name === null || $code === null) {
            throw new \Exception('Shipment method must have a name and code');
        }

        $order = $shipment->getOrder();
        if ($order === null) {
            throw new \Exception('Shipment must have an order');
        }

        $shippingCalculator = $shippingMethod->getCalculator();
        if ($shippingCalculator === null) {
            throw new \Exception('Shipment method must have a calculator');
        }

        $this->type = 'shipping_fee';
        $this->reference = $code;
        $this->name = $name;
        $this->quantity = $shipment->getShippingUnitCount();
        $this->quantityUnit = 'pcs';
        $this->unitPrice = $shipment->getShippingUnitTotal();
        $shippingTaxRate = $order->getAdjustmentsTotalRecursively('shipping_tax');
        $this->taxRate = $shippingTaxRate * 100;
        $this->totalAmount = $order->getShippingTotal();
        $this->totalDiscountAmount = 0;
        $this->totalTaxAmount = (int)(($shippingTaxRate / 100) * $this->totalAmount);
    }

    public function getLineItem(): LineItemInterface
    {
        return $this;
    }
}
