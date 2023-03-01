<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\BasicAuthenticationRetrieverInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\KlarnaRequestStructure;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\MerchantData;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Router\UrlGenerator;
use Payum\Core\Payum;
use Payum\Core\Security\TokenInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use SM\Factory\FactoryInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderPaymentTransitions;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\PaymentInterface as PaymentInterfaceAlias;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
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
        private CalculatorInterface $taxCalculator,
        private FactoryInterface $stateMachineFactory,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @psalm-suppress UndefinedClass
     *
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
                500,
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
                $requestStatus,
            );
        }

        if (is_string($klarnaOrderId)) {
            $this->addKlarnaReference($payment, $klarnaOrderId);
            $this->entityManager->persist($payment);
            if ($this->entityManager instanceof EntityManager) {
                $this->entityManager->flush($payment);
            } else {
                $this->entityManager->flush();
            }
        }

        return new JsonResponse(
            [
                'snippet' => $snippet,
            ],
        );
    }

    public function getConfirmationSnippet(string $tokenValue): Response
    {
        $payment = $this->getPaymentFromOrderToken($tokenValue);

        assert($payment instanceof PaymentInterface);

        $paymentDetails = $payment->getDetails();

        /** @var ?string $klarnaOrderId */
        $klarnaOrderId = $paymentDetails['klarna_order_id'] ?? null;

        if ($klarnaOrderId === null) {
            return new JsonResponse(
                ['error' => 'No associated klarna reference id with current payment details'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        /**
         * @psalm-suppress UndefinedClass (UnitEnum is supported as of PHP 8.1)
         *
         * @var string $klarnaUri
         */
        $klarnaUri = $this->parameterBag->get('north_creation_agency_sylius_klarna_gateway.checkout.uri');
        if (!str_ends_with($klarnaUri, '/')) {
            $klarnaUri .= '/';
        }
        $klarnaUri .= $klarnaOrderId;

        $paymentMethod = $payment->getMethod();
        assert($paymentMethod instanceof PaymentMethodInterface);
        $basicAuthString = $this->basicAuthenticationRetriever->getBasicAuthentication($paymentMethod);

        $response = $this->client->request(
            'GET',
            $klarnaUri,
            [
                'headers' => [
                'Authorization' => $basicAuthString,
                'Content-Type' => 'application/json',
                ],
            ],
        );

        $statusCode = $response->getStatusCode();

        if ($statusCode !== Response::HTTP_OK) {
            return new JsonResponse(
                ['error' => 'Could not retrieve order'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $content = $response->getBody()->getContents();

        /** @var array $data */
        $data = json_decode($content, true);

        /** @var ?string $snippet */
        $snippet = $data['html_snippet'] ?? null;
        if ($snippet === null) {
            return new JsonResponse(
                ['error' => 'Confirmation snippet could not be retrieved.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        return new JsonResponse(
            ['snippet' => $snippet],
            Response::HTTP_OK,
        );
    }

    public function confirm(Request $request): Response
    {
        $orderToken = $request->query->get('order_token') ?? '';
        $order = $this->orderRepository->findOneBy(['tokenValue' => $orderToken]);

        assert($order instanceof OrderInterface);

        $payment = $order->getLastPayment();
        assert($payment instanceof PaymentInterface);

        $method = $payment->getMethod();
        assert($method instanceof PaymentMethodInterface);

        try {
            $basicAuthString = $this->basicAuthenticationRetriever->getBasicAuthentication($method);

            $paymentDetails = $payment->getDetails();

            /** @var string $klarnaOrderId */
            $klarnaOrderId = $paymentDetails['klarna_order_id'] ?? '';

            /** @psalm-suppress UndefinedClass (UnitEnum is supported as of PHP 8.1)
             * @var string $pushConfirmationUrlTemplate
             */
            $pushConfirmationUrlTemplate = $this->parameterBag->get(
                'north_creation_agency_sylius_klarna_gateway.checkout.push_confirmation',
            );
            $pushConfirmationUrl = $this->replacePlaceholder($klarnaOrderId, $pushConfirmationUrlTemplate);
            $response = $this->client->request(
                'POST',
                $pushConfirmationUrl,
                [
                    'headers' => [
                        'Authorization' => $basicAuthString,
                        'Content-Type' => 'application/json',
                    ],
                ],
            );

            $status = $response->getStatusCode();
        } catch (\Exception $e) {
            $status = 400;
        }

        if ($status === 204) {
            $paymentState = $payment->getState();
            if ($paymentState !== PaymentInterfaceAlias::STATE_COMPLETED) {
                $this->updateState($order);
            }
        }

        $this->entityManager->flush();

        /** @var array $merchantData */
        $merchantData = $method->getGatewayConfig()?->getConfig()['merchantUrls'] ?? [];

        /** @var string $redirectUrl */
        $redirectUrl = $merchantData['confirmationUrl'] ?? $this->generateUrl('sylius_shop_homepage');

        if (!str_starts_with($redirectUrl, 'http')) {
            $router = null;

            try {
                /** @var RouterInterface $router */
                $router = $this->container->get('router');
            } catch (\Exception $e) {
                $this->redirectToRoute('sylius_shop_homepage');
            }

            assert($router instanceof RouterInterface);
            $urlGenerator = new UrlGenerator($router);
            $redirectUrl = $urlGenerator->generateAbsoluteURL($redirectUrl, ['tokenValue' => $orderToken]);
        }

        return $this->redirect($redirectUrl);
    }

    public function handlePush(Request $request, ?string $tokenValue = null): Response
    {
        if ($tokenValue === null) {
            $tokenValue = $request->query->get('token_value');
        }

        /** @var ?OrderInterface $order */
        $order = $this->orderRepository->findOneBy(['tokenValue' => $tokenValue]);
        if (null === $order) {
            return new JsonResponse(['error' => 'Order not found'], 404);
        }

        $klarnaOrderId = $request->query->get('klarna_order_id');
        if ($klarnaOrderId === null) {
            return new JsonResponse([
                'message' => 'No order was referenced. Expected klarna_order_id query parameter',
            ], 404);
        }

        try {
            /** @psalm-suppress UndefinedClass (UnitEnum is supported as of PHP 8.1) */
            $pushConfirmationUrlTemplate = $this->parameterBag->get(
                'north_creation_agency_sylius_klarna_gateway.checkout.push_confirmation',
            );
            assert(is_string($pushConfirmationUrlTemplate));
        } catch (ParameterNotFoundException $exception) {
            return new JsonResponse([
                'message' => 'Payment gateway not correctly configured. Make sure push_confirmation is set.',
            ], 500);
        }

        $pushConfirmationUrl = $this->replacePlaceholder('' . $klarnaOrderId, $pushConfirmationUrlTemplate);

        /** @var PaymentInterface $payment */
        $payment = $order->getPayments()->first();

        /** @var ?PaymentMethodInterface $method */
        $method = $payment->getMethod();
        if (null === $method) {
            return new JsonResponse([
                'message' => 'No associated payment method could be found',
            ], 404);
        }

        try {
            $basicAuthString = $this->basicAuthenticationRetriever->getBasicAuthentication($method);

            $response = $this->client->request(
                'POST',
                $pushConfirmationUrl,
                [
                    'headers' => [
                        'Authorization' => $basicAuthString,
                        'Content-Type' => 'application/json',
                    ],
                ],
            );
        } catch (\Exception $e) {
            return new JsonResponse(['message' => 'Something went wrong'], 500);
        }

        $status = $response->getStatusCode();
        if ($status === 204) {
            $paymentState = $payment->getState();
            if ($paymentState !== PaymentInterfaceAlias::STATE_COMPLETED) {
                $this->updateState($order);
            }
        }

        $this->entityManager->flush();

        return new JsonResponse(null, $status);
    }

    public function updateState(OrderInterface $order): void
    {
        $stateMachine = $this->stateMachineFactory->get($order, OrderPaymentTransitions::GRAPH);
        $stateMachine->apply(OrderPaymentTransitions::TRANSITION_PAY);

        $latestPayment = $order->getLastPayment();
        if (null === $latestPayment) {
            return;
        }

        $stateMachine = $this->stateMachineFactory->get($latestPayment, PaymentTransitions::GRAPH);
        $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function getToken(PaymentInterface $payment): TokenInterface
    {
        /** @psalm-suppress DeprecatedInterface */
        $tokenFactory = $this->payum->getTokenFactory();
        $paymentMethod = $payment->getMethod();
        Assert::isInstanceOf($paymentMethod, PaymentMethodInterface::class);

        $gatewayConfig = $paymentMethod->getGatewayConfig();
        Assert::notNull($gatewayConfig);

        $gatewayName = $gatewayConfig->getGatewayName();

        return $tokenFactory->createCaptureToken(
            $gatewayName,
            $payment,
            'north_creation_agency_sylius_klarna_gateway_confirm',
            ['order_token' => $payment->getOrder()?->getTokenValue()],
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

        $order = $payment->getOrder();
        if (null === $order) {
            return null;
        }

        /** @var string|null $pushUrl */
        $pushUrl = $this->getPushUrl($order);

        /** @var string|null $termsUrl */
        $termsUrl = $merchantData['termsUrl'] ?? null;

        /** @var string|null $checkoutUrl */
        $checkoutUrl = $merchantData['checkoutUrl'] ?? null;

        /** @var string|null $confirmationUrl */
        $confirmationUrl = $this->generateUrl(
            'north_creation_agency_sylius_klarna_gateway_confirm',
            ['order_token' => $payment->getOrder()?->getTokenValue() ?? ''],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        if (null === $termsUrl || null === $checkoutUrl || null === $confirmationUrl || null === $pushUrl) {
            return null;
        }

        try {
            /** @var RouterInterface $router */
            $router = $this->container->get('router');
            $urlGenerator = new UrlGenerator($router);
            $termsUrl = $urlGenerator->generateAbsoluteURL($termsUrl);
            $checkoutUrl = $urlGenerator->generateAbsoluteURL($checkoutUrl);
        } catch (\Exception $e) {
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

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Exception
     */
    protected function getPushUrl(OrderInterface $order): string
    {
        $router = $this->container->get('router');
        assert($router instanceof \Symfony\Component\Routing\RouterInterface);

        $pushUrl = $router->generate(
            'north_creation_agency_sylius_klarna_gateway_push',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $tokenValue = $order->getTokenValue();
        if ($tokenValue === null) {
            throw new \Exception('No order token was found.');
        }

        $pushUrl .= '?klarna_order_id={checkout.order.id}';
        $pushUrl .= '&token_value=' . $tokenValue;

        return $pushUrl;
    }

    protected function getPaymentFromOrderToken(string $tokenValue): ?PaymentInterfaceAlias
    {
        /** @var ?OrderInterface $order */
        $order = $this->orderRepository->findOneBy(['tokenValue' => $tokenValue]);

        assert($order instanceof OrderInterface);

        $payment = $order->getPayments()->first();

        return $payment !== false ? $payment : null;
    }

    private function addKlarnaReference(PaymentInterface $payment, string $reference): void
    {
        $details = $payment->getDetails();

        if (!array_key_exists('klarna_order_id', $details)) {
            $details['klarna_order_id'] = $reference;
        }

        $payment->setDetails($details);
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
}
