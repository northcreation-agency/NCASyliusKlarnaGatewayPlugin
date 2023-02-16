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
        $this->definedSetUp(includeTaxInPrice: true);
    }

    protected function definedSetUp(
        bool $includeTaxInPrice = true,
        int $variantPrice = 10000,
        bool $hasTax = true
    ): void
    {
        $taxRateMock = $this->createMock(TaxRate::class);
        $taxRateMock->method('getAmount')->willReturn($hasTax ? 0.1 : 0.0);
        $taxRateMock->method('isIncludedInPrice')->willReturn($hasTax ? $includeTaxInPrice : true);

        $taxRateResolver = $this->createMock(TaxRateResolverInterface::class);
        $taxRateResolver->method('resolve')->willReturn($hasTax ? $taxRateMock : null);

        $taxCalculator = $this->createMock(CalculatorInterface::class);
        $taxCalculator
            ->method('calculate')
            ->willReturn($hasTax ? 909.0 : 0.0);

        try {
            $this->orderLine = new OrderLine($this->createOrderItem($variantPrice), $taxRateResolver, $taxCalculator);
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

    public function testTaxNotIncludedInVariantPriceIsIncludedInOrderLineUnitPrice(): void
    {
        $variantPrice = 9091; // for a rate at 10%, a tax amount will equal 909 per item.
        $this->definedSetUp(includeTaxInPrice: false, variantPrice: $variantPrice);


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

        self::assertEquals($expectedStructure, $actualStructure);
    }

    public function testNoAssociatedTaxThrowsNoError(): void
    {
        $this->definedSetUp(hasTax: false);

        $expectedStructure = [
            "type" => "physical",
            "reference" => "19-402-USA",
            "name" => "T-Shirt - Red",
            "quantity" => 5,
            "quantity_unit" => "pcs",
            "unit_price" => 10000,
            "tax_rate" => 0,
            "total_amount" => 50000,
            "total_discount_amount" => 0,
            "total_tax_amount" => 0
        ];

        $actualStructure = $this->orderLine->toArray();

        Assert::assertEquals($expectedStructure, $actualStructure);
    }

    protected function createOrderItem(int $variantPrice = 10000): OrderItemInterface
    {
        $orderItemMock = $this->createMock(OrderItem::class);

        $product = $this->createProduct();
        $variant = $this->createProductVariant($product, $variantPrice);
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
            ->willReturn($variantPrice);
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

    protected function createProductVariant(Product $product, $variantPrice): ProductVariant
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
        $channelPricing->setPrice($variantPrice);
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
