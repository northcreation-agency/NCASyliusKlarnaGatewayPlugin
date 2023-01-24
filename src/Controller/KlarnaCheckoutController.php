<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Controller;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\BasicAuthenticationRetrieverInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\KlarnaRequestStructure;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\MerchantData;
use Payum\Core\Payum;
use Payum\Core\Security\TokenInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

class KlarnaCheckoutController extends AbstractController
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private BasicAuthenticationRetrieverInterface $basicAuthenticationRetriever,
        private TaxRateResolverInterface $taxRateResolver,
        private OrderProcessorInterface $shippingChargesProcessor,
        private Payum $payum,
    ) {
    }

    /**
     * @throws \Exception
     */
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
        $merchantData = $this->getMerchantData($payment);

        if (null === $merchantData) {
            return new JsonResponse([
                'error' => 'Merchant data not found',
            ], 404);
        }

        $klarnaRequestStructure = new KlarnaRequestStructure(
            order: $order,
            merchantData: $merchantData,
            taxRateResolver: $this->taxRateResolver,
            shippingChargesProcessor: $this->shippingChargesProcessor
        );

        $requestData = $klarnaRequestStructure->toArray();

        return new JsonResponse(
            [
                'snippet' => '<h1>Hello World</h1>',
                $requestData,

            ],
        );
    }


    /**
     * @psalm-suppress DeprecatedClass
     *
     * @param PaymentInterface $payment
     * @return TokenInterface
     * @throws \InvalidArgumentException
     */
    protected function getToken(PaymentInterface $payment): TokenInterface
    {
        $tokenFactory = $this->payum->getTokenFactory();
        $paymentMethod = $payment->getMethod();
        Assert::isInstanceOf($paymentMethod, PaymentMethodInterface::class);


        $gatewayConfig = $paymentMethod->getGatewayConfig();
        Assert::notNull($gatewayConfig);

        $gatewayName = $gatewayConfig->getGatewayName();

        return $tokenFactory->createCaptureToken(
            $gatewayName,
            $payment,
            'sylius_shop_homepage'
        );
    }

    protected function getMerchantData(PaymentInterface $payment): ?MerchantData
    {
        $method = $payment->getMethod();
        Assert::isInstanceOf($method, PaymentMethodInterface::class);

        /** @var ?array $merchantData */
        $merchantData = $method->getGatewayConfig()?->getConfig()['merchantUrls'] ?? null;
        if (null === $merchantData) {
            return null;
        }

        $pushUrl = $merchantData['pushUrl'] ?? null;
        $termsUrl = $merchantData['termsUrl'] ?? null;
        $checkoutUrl = $this->getPayumCaptureDoUrl($payment);
        $confirmationUrl = $merchantData['confirmationUrl'] ?? null;

        if (null === $termsUrl || null === $confirmationUrl || null === $pushUrl) {
            return null;
        }

        return new MerchantData($termsUrl, $checkoutUrl, $confirmationUrl, $pushUrl);
    }

    protected function getPayumCaptureDoUrl(PaymentInterface $payment): string
    {
        $router = $this->container->get('router');
        assert($router instanceof \Symfony\Component\Routing\RouterInterface);

        $token = $this->getToken($payment);
        $payumCaptureUrl = $router->generate(
            'payum_capture_do',
            [
                'payum_token' => $token->getHash(),
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return $payumCaptureUrl;
    }
}
