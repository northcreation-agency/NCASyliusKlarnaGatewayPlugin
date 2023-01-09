<?php

declare(strict_types=1);

namespace Tests\AndersBjorkland\SyliusKlarnaGatewayPlugin\Unit\Api\Checkout;

use AndersBjorkland\SyliusKlarnaGatewayPlugin\Api\Checkout\KlarnaRequestStructure;
use AndersBjorkland\SyliusKlarnaGatewayPlugin\Api\MerchantData;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\Channel;
use Sylius\Component\Core\Model\ChannelPricing;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItem;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductTranslation;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Core\Model\TaxRate;
use Sylius\Component\Product\Model\ProductVariantTranslation;
use Sylius\Component\Taxation\Model\TaxCategory;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;

class TestKlarnaRequestStructure extends TestCase
{
    private OrderInterface $order;
    private MerchantData $merchantData;
    private KlarnaRequestStructure $klarnaRequestStructure;

    public function setUp(): void
    {
        $this->order = $this->createOrder();
        $this->merchantData = $this->createMerchantData();

        $taxRateMock = $this->createMock(TaxRate::class);
        $taxRateMock->method('getAmount')->willReturn(0.1);

        $taxRateResolver = $this->createMock(TaxRateResolverInterface
        ::class);
        $taxRateResolver->method('resolve')->willReturn($taxRateMock);
        $this->klarnaRequestStructure = new KlarnaRequestStructure($this->order, $this->merchantData, $taxRateResolver);
    }

    public function testToArrayHasKlarnaRequestStructure(): void
    {

        $expectedStructure = [
            "purchase_country" => "GB",
                  "purchase_currency" => "GBP",
                  "locale" => "en-GB",
                  "order_amount" => 50000,
                  "order_tax_amount" => 4545,
                  "order_lines" => [
                      [
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
                      ]
                ],
                    "merchant_urls" => [
                        "terms" => "https://www.example.com/terms.html",
                        "checkout" => "https://www.example.com/checkout.html?order_id={checkout.order.id}",
                        "confirmation" => "https://www.example.com/confirmation.html?order_id={checkout.order.id}",
                        "push" => "https://www.example.com/api/push?order_id={checkout.order.id}"
                    ]
        ];

        $actualStructure = $this->klarnaRequestStructure->toArray();

        Assert::assertEquals($expectedStructure, $actualStructure);
    }

    protected function createOrder(): Order
    {
        // create mock of Order
        $order = $this->createMock(Order::class);

        // the Order mock should have getBillingAddress() method
        $billingAddress = $this->createMock(Address::class);
        $billingAddress->method('getCountryCode')->willReturn('GB');
        $order->method('getBillingAddress')
            ->willReturn($billingAddress);

        // the Order mock should have getCurrencyCode() method
        $order->method('getCurrencyCode')
            ->willReturn('GBP');

        // the Order mock should have getLocaleCode() method
        $order->method('getLocaleCode')
            ->willReturn('en-GB');

        // the Order mock should have getTotal() method
        $order->method('getTotal')
            ->willReturn(50000);

        // the Order mock should have getTaxTotal() method
        $order->method('getTaxTotal')
            ->willReturn(4545);

        // the Order mock should have getItems() method
        $order->method('getItems')
            ->willReturn($this->createOrderItems());


        return $order;
    }

    protected function createMerchantData(): MerchantData
    {
        return new MerchantData(
            'https://www.example.com/terms.html',
            'https://www.example.com/checkout.html?order_id={checkout.order.id}',
            'https://www.example.com/confirmation.html?order_id={checkout.order.id}',
            'https://www.example.com/api/push?order_id={checkout.order.id}'
        );
    }

    /**
     * @psalm-return  Collection<array-key, OrderItem>
     * return Collection|OrderItemInterface[]
     */
    protected function createOrderItems(): Collection
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

        return new ArrayCollection([$orderItemMock]);
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
