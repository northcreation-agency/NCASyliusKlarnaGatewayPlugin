<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Controller;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\BasicAuthenticationRetrieverInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class KlarnaCheckoutController extends AbstractController
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private BasicAuthenticationRetrieverInterface $basicAuthenticationRetriever,
    ) {
    }

    public function getSnippet(string $tokenValue): Response
    {
        /** @var ?OrderInterface $order */
        $order = $this->orderRepository->findOneBy(['tokenValue' => $tokenValue]);
        if (null === $order) {
            return new JsonResponse(['error' => 'Order not found'], 404);
        }

        /** @var PaymentInterface $payment */
        $payment = $order->getPayments()->first();

        /** @var ?PaymentMethodInterface $method */
        $method = $payment->getMethod();

        if (null === $method) {
            return new JsonResponse(['error' => 'Payment method not found'], 404);
        }

        $_basicAuthString = $this->basicAuthenticationRetriever->getBasicAuthentication($method);

        return new JsonResponse(
            [
                'snippet' => '<h1>Hello World</h1>',
            ],
        );
    }
}
