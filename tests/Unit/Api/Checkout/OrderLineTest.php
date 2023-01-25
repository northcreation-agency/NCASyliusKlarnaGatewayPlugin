<?php

declare(strict_types=1);

namespace Tests\NorthCreationAgency\SyliusKlarnaGatewayPlugin\Unit\Api\Checkout;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\OrderLine;
use PHPUnit\Framework\Assert;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductTranslation;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\Model\TaxRate;
use Sylius\Component\Product\Model\ProductVariantTranslation;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Model\TaxCategory;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;

class OrderLineTest extends \PHPUnit\Framework\TestCase
{
    private OrderLine $orderLine;

    public function setUp(): void
    {
        $taxRateMock = $this->createMock(TaxRate::class);
        $taxRateMock->method('getAmount')->willReturn(0.1);

        $taxRateResolver = $this->createMock(TaxRateResolverInterface::class);
        $taxRateResolver->method('resolve')->willReturn($taxRateMock);

        $taxCalculator = $this->createMock(CalculatorInterface::class);
        $taxCalculator
            ->method('calculate')
            ->willReturn(4545.0);

        try {
            $this->orderLine = new OrderLine($this->createOrderItem(), $taxRateResolver, $taxCalculator);
        } catch (\Exception $e) {
            Assert::fail($e->getMessage());
        }
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $expectedStructure = [
            "type" => "physical",
            "reference" => "19-402-USA",
            "name" => "T-Shirt - Red",
            "quantity" => 5,
            "quantity_unit" => "pcs",
            "unit_price" => 10000,
            "tax_rate" => 1000,
            "total_amount" => 50000,
            "total_discount_amount" => 0,
            "total_tax_amount" => 4545
        ];

        $actualStructure = $this->orderLine->toArray();

        Assert::assertEquals($expectedStructure, $actualStructure);
    }

    protected function createOrderItem(): OrderItemInterface
    {
        $orderItemMock = $this->createMock(OrderItem::class);

        $product = $this->createProduct();
        $variant = $this->createProductVariant($product);
        $orderItemMock->method('getVariant')
            ->willReturn($variant);
        $orderItemMock->method('getVariantName')
            ->willReturn('Red');
        $orderItemMock->method('getProductName')
            ->willReturn('T-Shirt');
        $orderItemMock->method('getQuantity')
            ->willReturn(5);
        $orderItemMock->method('getTotal')
            ->willReturn(50000);
        $orderItemMock->method('getUnitPrice')
            ->willReturn(10000);
        $orderItemMock->method('getTaxTotal')
            ->willReturn(4545);
        $orderItemMock->method('getDiscountedUnitPrice')
            ->willReturn(50000);

        return $orderItemMock;
    }

    protected function createProduct(): Product
    {
        $product = new Product();
        $product->setCode('19-402-USA');
        $product->setCurrentLocale('en_GB');

        $productTranslation = new ProductTranslation();
        $productTranslation->setName('Red T-Shirt');
        $product->addTranslation($productTranslation);

        $product->setName('Red T-Shirt');

        return $product;
    }

    protected function createProductVariant(Product $product): ProductVariant
    {
        $productVariant = new ProductVariant();
        $productVariant->setCode('19-402-USA');
        $productVariantTranslation = new ProductVariantTranslation();
        $productVariantTranslation->setName('Red T-Shirt');
        $productVariant->addTranslation($productVariantTranslation);
        $productVariant->setProduct($product);

        $taxCategory = $this->createTaxCategoryWithRate();
        $productVariant->setTaxCategory($taxCategory);

        $channel = new Channel();
        $channel->setCode('WEB_GB');

        $channelPricing = new ChannelPricing();
        $channelPricing->setChannelCode('WEB_GB');
        $channelPricing->setPrice(10000);
        $channelPricing->setProductVariant($productVariant);

        return $productVariant;
    }

    protected function createTaxCategoryWithRate(): TaxCategory
    {
        $taxCategory = new TaxCategory();
        $taxCategory->setCode('standard');
        $taxRate = new TaxRate();
        $taxRate->setAmount(0.1);
        $taxRate->setIncludedInPrice(true);
        $taxCategory->addRate($taxRate);

        return $taxCategory;
    }
}
