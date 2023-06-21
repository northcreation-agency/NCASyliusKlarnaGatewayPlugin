<?php

declare(strict_types=1);

namespace Tests\NorthCreationAgency\SyliusKlarnaGatewayPlugin\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\ClientInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\BasicAuthenticationRetrieverInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\PayloadDataResolver;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\DataUpdaterInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\OrderManagementInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Controller\KlarnaCheckoutController;
use Payum\Core\Payum;
use SM\Factory\FactoryInterface;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class KlarnaCheckoutControllerTest extends \PHPUnit\Framework\TestCase
{
    private KlarnaCheckoutController $controller;

    public function setUp(): void
    {
        $this->controller = new KlarnaCheckoutController(
            orderRepository: self::createMock(OrderRepositoryInterface::class),
            basicAuthenticationRetriever: self::createMock(BasicAuthenticationRetrieverInterface::class),
            taxRateResolver: self::createMock(TaxRateResolverInterface::class),
            shippingChargesProcessor:  self::createMock(OrderProcessorInterface::class),
            payum: self::createMock(Payum::class),
            parameterBag: self::createMock(ParameterBagInterface::class),
            client: self::createMock(ClientInterface::class),
            stateMachineFactory: self::createMock(FactoryInterface::class),
            entityManager: self::createMock(EntityManagerInterface::class),
            orderNumberAssigner: (new class implements OrderNumberAssignerInterface
            {
                public function assignNumber(\Sylius\Component\Order\Model\OrderInterface $order): void
                {
                    $order->setNumber(str_pad((string)$order->getId(), 9, '0' ));
                }
            }),
            payloadDataResolver: self::createMock(PayloadDataResolver::class),
            orderManagement: self::createMock(OrderManagementInterface::class),
            dataUpdater: self::createMock(DataUpdaterInterface::class),
        );
    }

    public function testReplacing(): void
    {
        $controller = $this->controller;

        $url = '/ordermanagement/v1/orders/{order_id}/acknowledge';
        $orderId = 'abc123';

        $expected = '/ordermanagement/v1/orders/abc123/acknowledge';
        $actual = $controller->replacePlaceholder($orderId, $url);

        self::assertEquals($expected, $actual);
    }
}
