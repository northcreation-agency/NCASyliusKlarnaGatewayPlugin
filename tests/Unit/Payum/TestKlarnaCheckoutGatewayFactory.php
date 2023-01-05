<?php

declare(strict_types=1);

namespace Tests\AndersBjorkland\SyliusKlarnaGatewayPlugin\Unit\Payum;

use AndersBjorkland\SyliusKlarnaGatewayPlugin\Payum\KlarnaApi;
use AndersBjorkland\SyliusKlarnaGatewayPlugin\Payum\KlarnaCheckoutGatewayFactory;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\GatewayFactoryInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;

class TestKlarnaCheckoutGatewayFactory extends TestCase
{
    private array $defaultConfig = [
        'payum.factory_name' => 'klarna_checkout',
        'payum.factory_title' => 'Klarna Checkout',
    ];

    private GatewayFactoryInterface $gatewayFactory;

    public function setUp(): void
    {
        $this->gatewayFactory = new KlarnaCheckoutGatewayFactory();
    }

    public function testCreateConfigHasCorrectFactoryName(): void
    {
        $actualConfig = $this->gatewayFactory->createConfig();

        $key = 'payum.factory_name';

        /** @var string $expectedValue */
        $expectedValue = $this->defaultConfig[$key];

        /** @var string $actualValue */
        $actualValue = $actualConfig[$key];

        Assert::assertEquals($expectedValue, $actualValue);
    }

    public function testFactoryReturnsClosureForApi(): void
    {
        $actualConfig = $this->gatewayFactory->createConfig();
        $closure = $actualConfig['payum.api'];

        Assert::assertInstanceOf(\Closure::class, $closure);
    }

    public function testApiClosureReturnsKlarnaApi(): void
    {
        $actualConfig = $this->gatewayFactory->createConfig();
        $closure = $actualConfig['payum.api'];

        assert($closure instanceof \Closure);
        $actualApi = $closure(new ArrayObject(['api_key' => 'test']));

        Assert::assertInstanceOf(KlarnaApi::class, $actualApi);
    }

}
