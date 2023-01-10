<?php

declare(strict_types=1);

namespace Tests\AndersBjorkland\SyliusKlarnaGatewayPlugin\Unit\Api;

use AndersBjorkland\SyliusKlarnaGatewayPlugin\Api\ShipmentLine;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\ShipmentInterface;
use Sylius\Component\Core\Model\ShippingMethod;
use Sylius\Component\Core\Model\TaxRate;
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
            ->willReturn(10);

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
        $shipment->method('getShippingUnitTotal')->willReturn(10000);

        $this->shipment = $shipment;
    }

    public function testShipmentLineToArray(): void
    {
        try {
            $shipmentLine = new ShipmentLine($this->shipment);
        } catch (\Exception $e) {
            Assert::fail($e->getMessage());
        }

        $expectedArray = [
            'type' => 'shipping_fee',
            'reference' => 'test',
            'name' => 'test',
            'quantity' => 1,
            'quantity_unit' => 'pcs',
            'unit_price' => 10000,
            'tax_rate' => 1000,
            'total_amount' => 1000,
            'total_discount_amount' => 0,
            'total_tax_amount' => 100,
        ];

        $actualArray = $shipmentLine->toArray();

        Assert::assertEquals($expectedArray, $actualArray);
    }
}
