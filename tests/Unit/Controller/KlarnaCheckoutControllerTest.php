<?php

declare(strict_types=1);

namespace Tests\NorthCreationAgency\SyliusKlarnaGatewayPlugin\Unit\Controller;

use GuzzleHttp\ClientInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\BasicAuthenticationRetrieverInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Controller\KlarnaCheckoutController;
use Payum\Core\Payum;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class KlarnaCheckoutControllerTest extends \PHPUnit\Framework\TestCase
{
    public function testReplacing(): void
    {
        $controller = new KlarnaCheckoutController(
            $this->createMock(OrderRepositoryInterface::class),
            $this->createMock(BasicAuthenticationRetrieverInterface::class),
            $this->createMock(TaxRateResolverInterface::class),
            $this->createMock(OrderProcessorInterface::class),
            $this->createMock(Payum::class),
            $this->createMock(ParameterBagInterface::class),
            $this->createMock(ClientInterface::class),
            $this->createMock(CalculatorInterface::class)
        );

        $url = '/ordermanagement/v1/orders/{order_id}/acknowledge';
        $orderId = 'abc123';

        $expected = '/ordermanagement/v1/orders/abc123/acknowledge';
        $actual = $controller->replacePlaceholder($orderId, $url);

        $this->assertEquals($expected, $actual);

    }
}
