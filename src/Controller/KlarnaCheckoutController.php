<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\BasicAuthenticationRetrieverInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\KlarnaRequestStructure;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\PayloadDataResolverInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Data\StatusDO;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\DataUpdaterInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\OrderManagementInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Router\UrlGenerator;
use Payum\Core\Payum;
use Payum\Core\Security\TokenInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use SM\Factory\FactoryInterface;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Bundle\PayumBundle\Model\GatewayConfigInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Sylius\Component\Core\OrderPaymentTransitions;
use Sylius\Component\Core\Repository\OrderRepositoryInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\Model\PaymentInterface as PaymentInterfaceAlias;
use Sylius\Component\Payment\PaymentTransitions;
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
        private FactoryInterface $stateMachineFactory,
        private EntityManagerInterface $entityManager,
        private OrderNumberAssignerInterface $orderNumberAssigner,
        private PayloadDataResolverInterface $payloadDataResolver,
        private OrderManagementInterface $orderManagement,
        private DataUpdaterInterface $dataUpdater,
    ) {
    }

    /**
     * @psalm-suppress UndefinedClass
     *
     * @throws \Exception
     */
    public function getSnippet(string $tokenValue, ?Request $request = null): Response
    {
        /** @var ?OrderInterface $order */
        $order = $this->orderRepository->findOneBy(['tokenValue' => $tokenValue]);
        if (null === $order) {
            return new JsonResponse(['error' => 'Order not found'], 404);
        }

        if (
            $order->getPaymentState() === OrderPaymentStates::STATE_PAID ||
            $order->getPaymentState() === OrderPaymentStates::STATE_AUTHORIZED
        ) {
            return new JsonResponse(['error' => 'Payment already processed'], Response::HTTP_GONE);
        }

        /** @var PaymentInterface $payment */
        $payment = $order->getPayments()->first();

        /** @var ?PaymentMethodInterface $method */
        $method = $payment->getMethod();

        if (null === $method) {
            return new JsonResponse(['error' => 'Payment method not found'], 404);
        }

        if ($this->isSupportedPaymentMethod($method) === false) {
            return new JsonResponse(['error' => 'Payment method not supported'], 404);
        }

        // transition payment to state "processing" if it is not already
//        $stateMachine = $this->stateMachineFactory->get($payment, PaymentTransitions::GRAPH);
//        if ($stateMachine->can(PaymentTransitions::TRANSITION_CREATE) === true) {
//            $stateMachine->apply(PaymentTransitions::TRANSITION_CREATE);
//            $stateMachine->apply(PaymentTransitions::TRANSITION_PROCESS);
//        } else if ( $stateMachine->can(PaymentTransitions::TRANSITION_PROCESS) === true) {
//            $stateMachine->apply(PaymentTransitions::TRANSITION_PROCESS);
//        } else if ($stateMachine->can(PaymentTransitions::TRANSITION_CANCEL) === true) {
//            $stateMachine->apply(PaymentTransitions::TRANSITION_CANCEL);
//
//
//            // create new payment similar to current $payment
//            $newPayment = new Payment();
//            $newPayment->setMethod($method);
//            $newPayment->setCurrencyCode($payment->getCurrencyCode());
//            $newPayment->setAmount($payment->getAmount());
//            $newPayment->setOrder($payment->getOrder());
//            $newPayment->setDetails($payment->getDetails());
//            $newPayment->setState(PaymentInterfaceAlias::STATE_PROCESSING);
//
//            $order->removePayment($payment);
//
//            $this->entityManager->persist($newPayment);
//
//            $this->entityManager->flush();
//
//            $payment = $newPayment;
//        } else {
//            return new JsonResponse(['error' => 'Payment could not be processed'], 404);
//        }

        $merchantData = $this->payloadDataResolver->getMerchantData($payment);

        if (null === $merchantData) {
            return new JsonResponse([
                'error' => 'Merchant data not found',
            ], 404);
        }

        $optionsData = $this->payloadDataResolver->getOptionsData($payment);

        $customerParams = [];
        if ($request !== null) {
            /** @var array<string, string> $customerParams */
            $customerParams = $request->query->all();
        }

        $klarnaRequestStructure = new KlarnaRequestStructure(
            order: $order,
            taxRateResolver: $this->taxRateResolver,
            shippingChargesProcessor: $this->shippingChargesProcessor,
            parameterBag: $this->parameterBag,
            orderNumberAssigner: $this->orderNumberAssigner,
            entityManager: $this->entityManager,
            merchantData: $merchantData,
            optionsData: $optionsData,
            customerData: $this->payloadDataResolver->getCustomerData($customerParams),
        );

        $requestData = $klarnaRequestStructure->toArray();

        $snippet = null;
        $errorMessage = '';
        $requestStatus = 200;

        try {
            $snippet = $this->orderManagement->fetchCheckoutWidget($requestData, $payment);
        } catch (ApiException $exception) {
            $errorMessage = $exception->getMessage();
            $requestStatus = $exception->getCode();
        }

        if (strlen($errorMessage) > 0) {
            return new JsonResponse(
                ['error_message' => $errorMessage],
                $requestStatus,
            );
        }

        $klarnaOrderId = $this->orderManagement->getKlarnaOrderId($payment);
        if (is_string($klarnaOrderId)) {
            $this->entityManager->persist($payment);
            $this->entityManager->flush();
        }

        return new JsonResponse(
            [
                'snippet' => $snippet,
            ],
        );
    }

    public function getConfirmationSnippet(string $tokenValue): Response
    {
        /** @var ?OrderInterface $order */
        $order = $this->orderRepository->findOneBy(['tokenValue' => $tokenValue]);
        assert($order instanceof OrderInterface);
        $this->confirm($order);

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

    public function confirm(OrderInterface $order): StatusDO
    {
        $errorMessage = null;
        $message = null;

        $klarnaData = $this->fetchOrderDataFromKlarna($order);

        try {
            $this->updateFromKlarna($klarnaData, $order);
        } catch (ApiException $e) {
        }

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
        } catch (GuzzleException $e) {
            $status = $e->getCode();
            $errorMessage = 'Could not retrieve data from Klarna.';
        } catch (\Exception $e) {
            $status = 500;
            $errorMessage = 'Server error.';
        }

        if ($status === Response::HTTP_NO_CONTENT) {
            if ($payment->getState() !== PaymentInterfaceAlias::STATE_COMPLETED) {
                $this->updateState($order);
                $message = 'Updated payment state. New state: ' . ($order->getPaymentState() ?? 'missing');
            }
        } else {
            $message = 'Payment state was not updated. Current state: ' . ($order->getPaymentState() ?? 'missing');
        }

        $this->entityManager->flush();

        return new StatusDO($status, $message, $errorMessage);
    }

    public function confirmHeadless(Request $request): Response
    {
        /** @var ?string $orderToken */
        $orderToken = $request->attributes->get('tokenValue') ?? '';
        $order = $this->orderRepository->findOneBy(['tokenValue' => $orderToken]);
        assert($order instanceof OrderInterface);

        $statusDO = $this->confirm($order);
        $status = $statusDO->getStatus();

        return match ($status) {
            Response::HTTP_NO_CONTENT => new JsonResponse(
                [
                'message' => $statusDO->getMessage()],
                Response::HTTP_OK,
            ),
            Response::HTTP_INTERNAL_SERVER_ERROR => new JsonResponse(
                [
                'error_message' => $statusDO->getErrorMessage()],
                $status,
            ),
            default => new JsonResponse(
                [
                'request_status' => $status,
                'error_message' => $statusDO->getErrorMessage()],
                Response::HTTP_BAD_REQUEST,
            )
        };
    }

    public function confirmWithRedirect(Request $request): Response
    {
        $orderToken = $request->query->get('order_token') ?? '';
        $order = $this->orderRepository->findOneBy(['tokenValue' => $orderToken]);
        assert($order instanceof OrderInterface);

        $method = $order->getLastPayment()?->getMethod();
        assert($method instanceof PaymentMethodInterface);

        $redirectUrl = $this->getConfirmationUrl($method, ['tokenValue' => $orderToken]);

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

    public function replacePlaceholder(string $replacement, string $string): string
    {
        $strStart = strpos($string, '{');
        $strEnd = strpos($string, '}');

        if ($strStart === false || $strEnd === false) {
            return $string;
        }

        return substr_replace($string, $replacement, $strStart, $strEnd - $strStart + 1);
    }

    /**
     * @throws ApiException
     */
    private function updateFromKlarna(array $data, OrderInterface $order): void
    {
        /** @var ?array $billingAddressData */
        $billingAddressData = $data['billing_address'] ?? null;
        if ($billingAddressData !== null) {
            $this->updateCustomer($billingAddressData, $order);
            $billingAddress = $order->getBillingAddress();
            if ($billingAddress !== null) {
                $this->updateAddress($billingAddressData, $billingAddress);
            }
        }

        /** @var ?array $shippingAddressData */
        $shippingAddressData = $data['shipping_address'] ?? null;
        if ($shippingAddressData !== null) {
            $shippingAddress = $order->getShippingAddress();
            if ($shippingAddress !== null) {
                $this->updateAddress($shippingAddressData, $shippingAddress);
            }
        }
    }

    private function fetchOrderDataFromKlarna(OrderInterface $order): array
    {
        $payment = $order->getPayments()->first();

        assert($payment instanceof PaymentInterface);

        $paymentDetails = $payment->getDetails();

        /** @var ?string $klarnaOrderId */
        $klarnaOrderId = $paymentDetails['klarna_order_id'] ?? null;
        assert($klarnaOrderId !== null);

        /** @psalm-suppress UndefinedClass (UnitEnum is supported as of PHP 8.1) */
        $readOrderUrlTemplate = $this->parameterBag->get(
            'north_creation_agency_sylius_klarna_gateway.checkout.read_order',
        );
        assert(is_string($readOrderUrlTemplate));

        $readOrderUrl = $this->replacePlaceholder('' . $klarnaOrderId, $readOrderUrlTemplate);

        /** @var PaymentInterface $payment */
        $payment = $order->getPayments()->first();

        /** @var ?PaymentMethodInterface $method */
        $method = $payment->getMethod();
        assert($method !== null);
        $basicAuthString = $this->basicAuthenticationRetriever->getBasicAuthentication($method);

        $response = $this->client->request(
            'GET',
            $readOrderUrl,
            [
                'headers' => [
                    'Authorization' => $basicAuthString,
                    'Content-Type' => 'application/json',
                ],
            ],
        );

        $dataContents = $response->getBody()->getContents();

        /** @var array $data */
        $data = json_decode($dataContents, true);

        return $data;
    }

    public function getConfirmationUrl(PaymentMethodInterface $method, array $parameters = []): string
    {
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
            $redirectUrl = $urlGenerator->generateAbsoluteURL($redirectUrl, $parameters);
        }

        return $redirectUrl;
    }

    /**
     * @throws ApiException
     */
    private function updateCustomer(array $addressData, OrderInterface $order): void
    {
        $customer = $order->getCustomer();
        assert($customer instanceof CustomerInterface);
        $updatedCustomer = $this->dataUpdater->updateCustomer($addressData, $customer);

        if ($customer->getEmail() !== $updatedCustomer->getEmail()) {
            $this->entityManager->persist($updatedCustomer);
            $order->setCustomer($updatedCustomer);
            $this->entityManager->persist($order);
        } else {
            $this->entityManager->persist($customer);
        }
    }

    /**
     * @throws ApiException
     */
    private function updateAddress(array $data, AddressInterface $address): void
    {
        $address = $this->dataUpdater->updateAddress($data, $address);

        $this->entityManager->persist($address);
    }

    private function isSupportedPaymentMethod(PaymentMethodInterface $paymentMethod): bool
    {
        $paymentConfig = $paymentMethod->getGatewayConfig();
        assert($paymentConfig instanceof GatewayConfigInterface);

        /** @psalm-suppress DeprecatedMethod */
        $factoryName = $paymentConfig->getFactoryName();

        return str_contains($factoryName, 'klarna_checkout');
    }
}
