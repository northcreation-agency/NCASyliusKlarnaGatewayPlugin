<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Event;

use GuzzleHttp\Exception\GuzzleException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Service\SupportedPaymentCheckerInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\StateMachine\CancelPaymentInterface;
use SM\SMException;
use Sylius\Bundle\ApiBundle\Command\Checkout\CompleteOrder;
use Sylius\Bundle\ApiBundle\CommandHandler\Checkout\CompleteOrderHandler;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

class GuardedCompleteOrderHandlerDecorator implements MessageHandlerInterface
{
    public function __construct(
        private CompleteOrderHandler $completeOrderHandler,
        private OrderRepositoryInterface $orderRepository,
        private CancelPaymentInterface $cancelPayment,
        private SupportedPaymentCheckerInterface $supportedPaymentChecker,
    ) {
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     * @throws SMException
     */
    public function __invoke(CompleteOrder $completeOrder): OrderInterface
    {
        try {
            $order = $this->completeOrderHandler->__invoke($completeOrder);
        } catch (SMException $exception) {
            $this->cancelKlarnaOrderOnCompleteException($completeOrder);

            throw $exception;
        }

        return $order;
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     * @throws SMException
     */
    private function cancelKlarnaOrderOnCompleteException(CompleteOrder $completeOrder): void
    {
        $tokenValue = $completeOrder->getOrderTokenValue();
        assert(is_string($tokenValue));

        $cart = $this->orderRepository->findOneBy(['tokenValue' => $tokenValue]);
        assert($cart instanceof OrderInterface);

        $payments = $cart->getPayments();
        foreach ($payments as $payment) {
            if (!$payment instanceof PaymentInterface) {
                continue;
            }

            $method = $payment->getMethod();
            if (!$method instanceof PaymentMethodInterface) {
                continue;
            }

            if ($this->supportedPaymentChecker->supportsPaymentMethod($method)) {
                $this->cancelPayment->cancel($payment);
            }
        }
    }
}
