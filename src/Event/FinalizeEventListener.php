<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Event;

use SM\Factory\FactoryInterface;
use Sylius\Bundle\ResourceBundle\Event\ResourceControllerEvent;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderPaymentTransitions;
use Sylius\Component\Payment\PaymentTransitions;

class FinalizeEventListener implements FinalizeEventListenerInterface
{
    public function __construct(
        private FactoryInterface $stateMachineFactory,
    ) {
    }

    public function checkPayment(OrderInterface $order): void
    {
        $this->updateState($order);
    }

    protected function supportedEvent(ResourceControllerEvent $event): bool
    {
        /** @var OrderInterface|mixed $order */
        $order = $event->getSubject();

        return $order instanceof OrderInterface;
    }

    protected function updateState(OrderInterface $order): void
    {
        $stateMachine = $this->stateMachineFactory->get($order, OrderPaymentTransitions::GRAPH);
        $stateMachine->apply(OrderPaymentTransitions::TRANSITION_AUTHORIZE);

        $latestPayment = $order->getLastPayment();
        if (null === $latestPayment) {
            return;
        }

        $stateMachine = $this->stateMachineFactory->get($latestPayment, PaymentTransitions::GRAPH);
        $stateMachine->apply(PaymentTransitions::TRANSITION_AUTHORIZE);
    }
}
