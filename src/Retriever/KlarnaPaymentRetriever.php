<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Retriever;

use Payum\Core\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

class KlarnaPaymentRetriever implements KlarnaPaymentRetrieverInterface
{
    public function retrieveFromOrder(OrderInterface $order): ?PaymentInterface
    {
        /** @var ?PaymentInterface $klarnaPayment */
        $klarnaPayment = null;

        $payments = $order->getPayments();

        /** @var PaymentInterface $payment */
        foreach ($payments as $payment) {
            $method = $payment->getMethod();
            assert($method instanceof PaymentMethodInterface);

            if ($this->supportsPaymentMethod($method)) {
                $klarnaPayment = $payment;

                break;
            }
        }

        return $klarnaPayment;
    }

    private function supportsPaymentMethod(PaymentMethodInterface $paymentMethod): bool
    {
        $paymentConfig = $paymentMethod->getGatewayConfig();
        assert($paymentConfig instanceof GatewayConfigInterface);

        /** @psalm-suppress DeprecatedMethod */
        $factoryName = $paymentConfig->getFactoryName();

        return str_contains($factoryName, 'klarna_checkout');
    }
}
