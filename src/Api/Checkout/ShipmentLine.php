<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout;

use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;

class ShipmentLine extends AbstractLineItem
{
    /**
     * @throws \Exception
     */
    public function __construct(
        ShipmentInterface $shipment,
        OrderProcessorInterface $shippingChargesProcessor,
    ) {
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

        $shippingChargesProcessor->process($order);

        $shippingCharge = 0;
        $shippingAdjustments = $shipment->getAdjustments(AdjustmentInterface::SHIPPING_ADJUSTMENT);

        foreach ($shippingAdjustments as $adjustment) {
            $shippingCharge += $adjustment->getAmount();
        }

        $shippingTaxTotal = 0;
        $shippingTaxAdjustments = $shipment->getAdjustments(AdjustmentInterface::TAX_ADJUSTMENT);
        $shippingTaxRate = 0;

        foreach ($shippingTaxAdjustments as $adjustment) {
            $shippingTaxTotal += $adjustment->getAmount();
            $details = $adjustment->getDetails();
            if (isset($details['taxRateAmount']) && is_numeric($details['taxRateAmount'])) {
                $shippingTaxRate = (int) ($details['taxRateAmount'] * 100 * 100);
            } else {
                throw new \Exception('Shipping tax adjustment must have a tax rate amount');
            }
        }

        $this->type = 'shipping_fee';
        $this->reference = $code;
        $this->name = $name;
        $this->quantity = $shipment->getShippingUnitCount();
        $this->quantityUnit = 'pcs';
        $this->unitPrice = $shipment->getShippingUnitTotal();
        $this->taxRate = $shippingTaxRate;
        $this->totalAmount = $shippingCharge;
        $this->totalDiscountAmount = 0;
        $this->totalTaxAmount = $shippingTaxTotal;
    }

    public function getLineItem(): LineItemInterface
    {
        return $this;
    }
}
