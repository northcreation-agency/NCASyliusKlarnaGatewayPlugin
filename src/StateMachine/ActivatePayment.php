<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\StateMachine;

use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\GuzzleException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\KlarnaRequestStructure;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Data\StatusDO;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\OrderManagementInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Retriever\KlarnaPaymentRetriever;
use Payum\Core\Model\GatewayConfigInterface;
use SM\Factory\Factory;
use SM\SMException;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderPaymentTransitions;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ActivatePayment implements ActivatePaymentInterface
{
    public function __construct(
        private TaxRateResolverInterface $taxRateResolver,
        private OrderProcessorInterface $shippingChargesProcessor,
        private ParameterBagInterface $parameterBag,
        private OrderNumberAssignerInterface $orderNumberAssigner,
        private EntityManagerInterface $entityManager,
        private Factory $stateMachineFactory,
        private OrderManagementInterface $orderManagement,
    ) {
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     * @throws SMException
     */
    public function activate(OrderInterface $order): void
    {
        $payment = $order->getLastPayment();

        assert($payment instanceof PaymentInterface);

        $paymentMethod = $payment->getMethod();
        assert($paymentMethod instanceof PaymentMethodInterface);
        if (!$this->supportsPaymentMethod($paymentMethod)) {
            return;
        }

        $statusCode = $this->sendCaptureRequest($payment);

        if ($statusCode === StatusDO::PAYMENT_CAPTURED) {
            $this->handlePaidOrder($order);
        }

        if ($statusCode === StatusDO::PAYMENT_ALREADY_CAPTURED) {
            $this->handlePaidOrder($order);
        }
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     * @throws \Exception
     */
    public function sendCaptureRequest(SyliusPaymentInterface $payment): int
    {
        $paymentMethod = $payment->getMethod();
        assert($paymentMethod instanceof PaymentMethodInterface);

        $order = $payment->getOrder();
        assert($order instanceof OrderInterface);

        $klarnaRequestStructure = new KlarnaRequestStructure(
            order: $order,
            taxRateResolver: $this->taxRateResolver,
            shippingChargesProcessor: $this->shippingChargesProcessor,
            parameterBag: $this->parameterBag,
            orderNumberAssigner: $this->orderNumberAssigner,
            entityManager: $this->entityManager,
            type: KlarnaRequestStructure::CAPTURE,
        );

        $payload = $klarnaRequestStructure->toArray();

        return $this->orderManagement->sendCaptureRequest($payment, $payload);
    }

    public function replacePlaceholder(string $replacement, string $string): string
    {
        $strStart = strpos($string, '{');
        $strEnd = strpos($string, '}');

        if ($strStart === false || $strEnd === false) {
            return $string;
        }

        return substr_replace($string, $replacement, $strStart, $strEnd - $strStart + 1);
    }

    public function supportsPaymentMethod(PaymentMethodInterface $paymentMethod): bool
    {
        $paymentConfig = $paymentMethod->getGatewayConfig();
        assert($paymentConfig instanceof GatewayConfigInterface);

        /** @psalm-suppress DeprecatedMethod */
        $factoryName = $paymentConfig->getFactoryName();

        return str_contains($factoryName, 'klarna_checkout');
    }

    /**
     * @throws SMException
     */
    private function handlePaidOrder(OrderInterface $order): void
    {
        $stateMachine = $this->stateMachineFactory->get($order, OrderPaymentTransitions::GRAPH);
        $stateMachine->apply(OrderPaymentTransitions::TRANSITION_PAY);

        $retriever = new KlarnaPaymentRetriever();
        $klarnaPayment = $retriever->retrieveFromOrder($order);
        if (null === $klarnaPayment) {
            return;
        }

        $stateMachine = $this->stateMachineFactory->get($klarnaPayment, PaymentTransitions::GRAPH);
        $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);
    }
}
