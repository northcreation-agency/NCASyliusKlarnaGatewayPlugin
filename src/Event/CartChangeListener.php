<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Event;

use Doctrine\ORM\Event\PreUpdateEventArgs;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\StateMachine\UpdateCheckoutInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

class CartChangeListener
{
    public const SUPPORTED_CART_FIELDS = [
        'total',
        'itemsTotal',
    ];

    public function __construct(
        private UpdateCheckoutInterface $updateCheckout,
    ) {
    }

    public function preUpdate(PreUpdateEventArgs $event): void
    {
        $object = $event->getObject();
        if (!$this->isSupported($event)) {
            return;
        }

        if ($object instanceof OrderInterface) {
            $this->updateCheckout->updateOrder($object);
        }
    }

    protected function isSupported(PreUpdateEventArgs $event): bool
    {
        $object = $event->getObject();

        if (!$object instanceof \Sylius\Component\Core\Model\OrderInterface) {
            return false;
        }

        $payment = $object->getLastPayment();
        if (!$payment instanceof PaymentInterface) {
            return false;
        }

        $paymentMethod = $payment->getMethod();
        if (!$paymentMethod instanceof PaymentMethodInterface) {
            return false;
        }

        $paymentConfig = $paymentMethod->getGatewayConfig();
        assert($paymentConfig instanceof GatewayConfigInterface);

        /** @psalm-suppress DeprecatedMethod */
        $factoryName = $paymentConfig->getFactoryName();

        $isKlarna = str_contains($factoryName, 'klarna');
        if (!$isKlarna) {
            return false;
        }

        if (!$this->hasKlarnaSession($object)) {
            return false;
        }

        $changeSetKeys = array_keys($event->getEntityChangeSet());

        $state = $object->getState();
        if ($state !== \Sylius\Component\Order\Model\OrderInterface::STATE_CART) {
            return false;
        }

        $paymentState = $payment->getState();
        if ($paymentState !== \Sylius\Component\Payment\Model\PaymentInterface::STATE_CART) {
            return false;
        }

        return count(array_intersect($changeSetKeys, self::SUPPORTED_CART_FIELDS)) > 0;
    }

    protected function hasKlarnaSession(OrderInterface $order): bool
    {
        $payment = $order->getLastPayment();

        if ($payment === null) {
            return false;
        }

        $paymentDetails = $payment->getDetails();

        return ($paymentDetails['klarna_order_id'] ?? null) !== null;
    }
}
