<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\StateMachine;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Data\StatusDO;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Verifier\OrderVerifier;
use Payum\Core\Model\GatewayConfigInterface;
use SM\Factory\FactoryInterface;
use SM\SMException;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderPaymentTransitions;
use Sylius\Component\Payment\PaymentTransitions;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class UpdatePayment
{
    public function __construct(
        private FactoryInterface $stateMachineFactory,
        private OrderVerifier $orderVerifier,
        private ParameterBagInterface $parameterBag,
    ) {
    }

    /**
     * @throws ApiException
     * @throws SMException
     */
    public function afterCreateOrder(OrderInterface $order): void
    {
        if (!$this->supportsOrder($order)) {
            return;
        }
        $status = $this->orderVerifier->verify($order);

        if ($status->getStatus() === StatusDO::PAYMENT_CONFIRMED) {
            $this->orderVerifier->update($order);

            $stateMachine = $this->stateMachineFactory->get($order, OrderPaymentTransitions::GRAPH);
            $stateMachine->apply(OrderPaymentTransitions::TRANSITION_PAY);

            $latestPayment = $order->getLastPayment();
            if (null === $latestPayment) {
                return;
            }

            $stateMachine = $this->stateMachineFactory->get($latestPayment, PaymentTransitions::GRAPH);
            $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);
        } else {
            /** @psalm-suppress UndefinedClass (UnitEnum is supported as of PHP 8.1)
             * @var bool $silentException
             */
            $silentException = $this->parameterBag->get(
                'north_creation_agency_sylius_klarna_gateway.checkout.silent_exception',
            ) ?? false;
            if (!$silentException) {
                throw new ApiException('Could not verify payment with Klarna');
            }
        }
    }

    private function supportsOrder(OrderInterface $order): bool
    {
        $payment = $order->getLastPayment();
        assert($payment instanceof PaymentInterface);

        $method = $payment->getMethod();
        assert($method instanceof PaymentMethodInterface);

        $gatewayConfig = $method->getGatewayConfig();
        assert($gatewayConfig instanceof  GatewayConfigInterface);

        /** @psalm-suppress DeprecatedMethod */
        $factoryName = $gatewayConfig->getFactoryName();

        return str_contains($factoryName, 'klarna_checkout');
    }
}
