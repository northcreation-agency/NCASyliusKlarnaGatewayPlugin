<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Controller;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WidgetRenderController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{
    public function __construct(
        private KlarnaCheckoutController $klarnaCheckoutController,
        private OrderRepositoryInterface $orderRepository,
    ) {
    }

    public function widget(string $tokenValue, Request $request): Response
    {
        $snippetResponse = $this->klarnaCheckoutController->getSnippet($tokenValue, $request);
        $content = $snippetResponse->getContent();

        if ($content === false) {
            $content = '';
        }

        /** @var array $snippet */
        $snippet = json_decode($content, true);

        if ($snippetResponse->getStatusCode() === Response::HTTP_GONE) {
            $order = $this->orderRepository->findOneBy(['tokenValue' => $tokenValue]);
            assert($order instanceof OrderInterface);

            if ($order->getPaymentState() === OrderPaymentStates::STATE_PAID) {
                $method = $order->getLastPayment()?->getMethod();
                assert($method instanceof PaymentMethodInterface);
                $confirmationUrl = $this->klarnaCheckoutController->getConfirmationUrl(
                    $method,
                    ['tokenValue' => $tokenValue],
                );

                return $this->redirect($confirmationUrl);
            }
        }

        return $this->render(
            '@NorthCreationAgencySyliusKlarnaGatewayPlugin/widget.html.twig',
            ['snippet' => $snippet['snippet'] ?? ''],
        );
    }

    public function confirmationWidget(string $tokenValue): Response
    {
        $snippetResponse = $this->klarnaCheckoutController->getConfirmationSnippet($tokenValue);
        $content = $snippetResponse->getContent();

        if ($content === false) {
            $content = '';
        }

        /** @var array $data */
        $data = json_decode($content, true);

        return $this->render(
            '@NorthCreationAgencySyliusKlarnaGatewayPlugin/widget.html.twig',
            ['snippet' => $data['snippet'] ?? ''],
        );
    }
}
