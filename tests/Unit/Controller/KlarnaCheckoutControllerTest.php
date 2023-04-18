<?php

declare(strict_types=1);

namespace Tests\NorthCreationAgency\SyliusKlarnaGatewayPlugin\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\ClientInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\BasicAuthenticationRetrieverInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Controller\KlarnaCheckoutController;
use Payum\Core\Payum;
use SM\Factory\FactoryInterface;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Core\Model\Order;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;

class KlarnaCheckoutControllerTest extends \PHPUnit\Framework\TestCase
{
    private KlarnaCheckoutController $controller;

    public function setUp(): void
    {
        $this->controller = new KlarnaCheckoutController(
            orderRepository: $this->createMock(OrderRepositoryInterface::class),
            basicAuthenticationRetriever: $this->createMock(BasicAuthenticationRetrieverInterface::class),
            taxRateResolver: $this->createMock(TaxRateResolverInterface::class),
            shippingChargesProcessor:  $this->createMock(OrderProcessorInterface::class),
            payum: $this->createMock(Payum::class),
            parameterBag: $this->createMock(ParameterBagInterface::class),
            client: $this->createMock(ClientInterface::class),
            taxCalculator: $this->createMock(CalculatorInterface::class),
            stateMachineFactory: $this->createMock(FactoryInterface::class),
            entityManager: $this->createMock(EntityManagerInterface::class),
            orderNumberAssigner: (new class implements OrderNumberAssignerInterface
            {
                public function assignNumber(\Sylius\Component\Order\Model\OrderInterface $order): void
                {
                    $order->setNumber(str_pad((string)$order->getId(), 9, '0' ));
                }
            }),
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
