<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Controller;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
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
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webmozart\Assert\Assert;

class KlarnaCheckoutController extends AbstractController
{
    public const WIDGET_SNIPPET_KEY = 'html_snippet';

    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private BasicAuthenticationRetrieverInterface $basicAuthenticationRetriever,
        private TaxRateResolverInterface $taxRateResolver,
        private OrderProcessorInterface $shippingChargesProcessor,
        private Payum $payum,
        private ParameterBagInterface $parameterBag,
        private ClientInterface $client,
        private CalculatorInterface $taxCalculator
    ) {
    }

    /**
     * @psalm-suppress UndefinedClass
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

        $basicAuthString = $this->basicAuthenticationRetriever->getBasicAuthentication($method);
        $merchantData = $this->getMerchantData($payment);

        if (null === $merchantData) {
            return new JsonResponse([
                'error' => 'Merchant data not found',
            ], 404);
        }

        /** @var string $klarnaUri */
        $klarnaUri = $this->parameterBag->get('north_creation_agency_sylius_klarna_gateway.checkout.uri');

        $klarnaRequestStructure = new KlarnaRequestStructure(
            order: $order,
            merchantData: $merchantData,
            taxRateResolver: $this->taxRateResolver,
            shippingChargesProcessor: $this->shippingChargesProcessor,
            taxCalculator: $this->taxCalculator,
        );

        $requestData = $klarnaRequestStructure->toArray();

        $snippet = null;
        $klarnaOrderId = null;
        $errorMessage = '';
        $requestStatus = 200;
        try {
            $response = $this->client->request(
                'POST',
                $klarnaUri,
                [
                    'headers' => [
                        'Authorization' => $basicAuthString,
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode($requestData),
                ],
            );

            $contents = json_decode($response->getBody()->getContents(), true);
            assert(is_array($contents));

            /** @var string|null $klarnaOrderId */
            $klarnaOrderId = $contents['order_id'] ?? null;

            /** @var string $snippet */
            $snippet = $contents[self::WIDGET_SNIPPET_KEY] ?? throw new \Exception(
                'Expected to find key ' . self::WIDGET_SNIPPET_KEY . ' but none were found in response.',
                500
            );

        } catch (RequestException $e) {
            $response = $e->getResponse();
            $requestStatus = $response !== null ? $response->getStatusCode() : 403;
            $errorMessage =
                $response !== null ?
                $response->getBody()->getContents() : 'Something went wrong with request.'
            ;
        } catch (GuzzleException $e) {
            $requestStatus = 500;
            $errorMessage = $e->getMessage();
        }

        if (strlen($errorMessage) > 0) {
            return new JsonResponse(
                ['error_message' => $errorMessage],
                $requestStatus
            );
        }

        if (is_string($klarnaOrderId)) {
            $this->addKlarnaReference($payment, $klarnaOrderId);
        }

        return new JsonResponse(
            [
                'snippet' => $snippet,
                'request_structure' => $requestData
            ],
        );
    }

    public function handlePush(string $tokenValue, Request $request): Response
    {
        /** @var ?OrderInterface $order */
        $order = $this->orderRepository->findOneBy(['tokenValue' => $tokenValue]);
        if (null === $order) {
            return new JsonResponse(['error' => 'Order not found'], 404);
        }

        $klarnaOrderId = $request->query->get('klarna_order_id');
        if ($klarnaOrderId === null) {
            return new JsonResponse([
                'message' => 'No order was referenced. Expected klarna_order_id query parameter'
            ], 404);
        }

        /** @var string $pushConfirmationUrl */
        $pushConfirmationUrlTemplate = $this->parameterBag->get(
            'north_creation_agency_sylius_klarna_gateway.checkout.push_confirmation'
        );

        /** @var string $pushConfirmationUrl */
        $pushConfirmationUrl = $this->replacePlaceholder($klarnaOrderId, $pushConfirmationUrlTemplate);

        try {
            /** @var PaymentInterface $payment */
            $payment = $order->getPayments()->first();

            /** @var ?PaymentMethodInterface $method */
            $method = $payment->getMethod();

            $basicAuthString = $this->basicAuthenticationRetriever->getBasicAuthentication($method);

            $response = $this->client->request(
                'POST',
                $pushConfirmationUrl,
                [
                    'headers' => [
                        'Authorization' => $basicAuthString,
                        'Content-Type' => 'application/json',
                    ],
                ]
            );
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Something went wrong'], 500);
        }

        return new JsonResponse(null, 204);
    }

    /**
     * @psalm-suppress DeprecatedClass
     *
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
            'sylius_shop_homepage',
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

        /** @var string|null $pushUrl */
        $pushUrl = $this->getPushUrl($payment->getOrder());

        /** @var string|null $termsUrl */
        $termsUrl = $merchantData['termsUrl'] ?? null;

        $checkoutUrl = $merchantData['checkoutUrl'] ?? null;

        /** @var string|null $confirmationUrl */
        $confirmationUrl = $this->getPayumCaptureDoUrl($payment);

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
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        return $payumCaptureUrl;
    }

    protected function getPushUrl(OrderInterface $order): string
    {
        $router = $this->container->get('router');
        assert($router instanceof \Symfony\Component\Routing\RouterInterface);

        $pushUrl = $router->generate(
            'api_orders_shop_klarna_checkout_push_item',
            [
                'tokenValue' => $order->getTokenValue()
            ],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $pushUrl .= '?klarna_order_id={checkout.order.id}';

        return $pushUrl;
    }

    private function addKlarnaReference(PaymentInterface $payment, string $reference): void
    {
        $details = $payment->getDetails();

        if (!array_key_exists('klarna_order_id', $details)) {
            $details['klarna_order_id'] = $reference;
        }

        $payment->setDetails($details);
    }

    public function replacePlaceholder($replacement, $string): string
    {
        $strStart = strpos($string, '{');
        $strEnd = strpos($string, '}');

        return substr_replace($string, $replacement, $strStart, $strEnd - $strStart + 1);
    }
}
