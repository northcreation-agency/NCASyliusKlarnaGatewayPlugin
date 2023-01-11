<?php

declare(strict_types=1);

namespace Tests\AndersBjorkland\SyliusKlarnaGatewayPlugin\Unit\Api\Checkout;

use AndersBjorkland\SyliusKlarnaGatewayPlugin\Api\Checkout\ShipmentLine;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Adjustment;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Model\ShippingMethod;
use Sylius\Component\Core\Model\TaxRate;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Taxation\Model\TaxCategory;

class ShipmentLineTest extends TestCase
{
    private ShipmentInterface $shipment;

    public function setUp(): void
    {
        $taxRate = $this->createMock(TaxRate::class);
        $taxRate->method('getAmount')->willReturn(0.1);

        $taxCategory = $this->createMock(TaxCategory::class);
        $ratesCollection = new ArrayCollection([$taxRate]);
        $taxCategory->method('getRates')->willReturn($ratesCollection);

        $order = $this->createMock(Order::class);
        $order->method('getShippingTotal')->willReturn(1000);
        $order->method('getAdjustmentsTotalRecursively')
            ->with('shipping_tax')
            ->willReturn(100);

        $shippingMethod = new ShippingMethod();
        $shippingMethod->setCurrentLocale('en');
        $shippingMethod->setName('test');
        $shippingMethod->setCode('test');
        $shippingMethod->setTaxCategory($taxCategory);
        $shippingMethod->setCalculator('default');

        $shipment = $this->createMock(ShipmentInterface::class);
        $shipment->method('getMethod')->willReturn($shippingMethod);
        $shipment->method('getOrder')->willReturn($order);
        $shipment->method('getShippingUnitCount')->willReturn(1);
        $shipment->method('getShippingUnitTotal')->willReturn(11000);

        $shippingAdjustment = new Adjustment();
        $shippingAdjustment->setType(AdjustmentInterface::SHIPPING_ADJUSTMENT);
        $baseCost = 10000;
        $tax = 1000;
        $shippingAdjustment->setAmount($baseCost + $tax);

        $shippingTaxAdjustment = new Adjustment();
        $shippingTaxAdjustment->setType(AdjustmentInterface::TAX_ADJUSTMENT);
        $shippingTaxAdjustment->setAmount($tax);
        $shippingTaxAdjustment->setDetails(['taxRateAmount' => 0.1]);

        $shipment
            ->method('getAdjustments')
            ->willReturnOnConsecutiveCalls(
                (new ArrayCollection([$shippingAdjustment])),
                (new ArrayCollection([$shippingTaxAdjustment]))
            );

        $this->shipment = $shipment;
    }

    /**
     * @throws \Exception
     */
    public function testShipmentLineToArray(): void
    {
        $shipmentLine = new ShipmentLine($this->shipment, $this->createMock(OrderProcessorInterface::class));

        $expectedArray = [
            'type' => 'shipping_fee',
            'reference' => 'test',
            'name' => 'test',
            'quantity' => 1,
            'quantity_unit' => 'pcs',
            'unit_price' => 11000,
            'tax_rate' => 1000,
            'total_amount' => 11000,
            'total_discount_amount' => 0,
            'total_tax_amount' => 1000,
        ];

        $actualArray = $shipmentLine->toArray();

        Assert::assertEquals($expectedArray, $actualArray);
    }
}
