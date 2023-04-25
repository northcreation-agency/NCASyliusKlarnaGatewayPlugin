<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\StateMachine;

use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\BasicAuthenticationRetrieverInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\KlarnaRequestStructure;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Data\StatusDO;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use Payum\Core\Model\GatewayConfigInterface;
use SM\Factory\Factory;
use SM\SMException;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\OrderPaymentTransitions;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Payment\PaymentTransitions;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;

class ActivatePayment implements ActivatePaymentInterface
{
    public function __construct(
        private ClientInterface $client,
        private TaxRateResolverInterface $taxRateResolver,
        private OrderProcessorInterface $shippingChargesProcessor,
        private ParameterBagInterface $parameterBag,
        private BasicAuthenticationRetrieverInterface $basicAuthenticationRetriever,
        private OrderNumberAssignerInterface $orderNumberAssigner,
        private EntityManagerInterface $entityManager,
        private Factory $stateMachineFactory,
    ) {
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     * @throws SMException
     */
    public function activate(OrderInterface $order): void
    {
        $payment = $order->getLastPayment();

        assert($payment instanceof PaymentInterface);

        $paymentMethod = $payment->getMethod();
        assert($paymentMethod instanceof PaymentMethodInterface);
        if (!$this->supportsPaymentMethod($paymentMethod)) {
            return;
        }

        $statusCode = $this->sendCaptureRequest($payment);
        if ($statusCode === StatusDO::PAYMENT_CAPTURED) {
            $this->handlePaidOrder($order);
        }

        if ($statusCode === StatusDO::PAYMENT_ALREADY_CAPTURED) {
            $this->handlePaidOrder($order);
        }
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     */
    public function sendCaptureRequest(SyliusPaymentInterface $payment): int
    {
        $paymentMethod = $payment->getMethod();
        assert($paymentMethod instanceof PaymentMethodInterface);

        $paymentDetails = $payment->getDetails();

        $order = $payment->getOrder();
        assert($order instanceof OrderInterface);

        $basicAuthString = $this->basicAuthenticationRetriever->getBasicAuthentication($paymentMethod);

        /** @var ?string $klarnaOrderId */
        $klarnaOrderId = $paymentDetails['klarna_order_id'] ?? null;
        assert($klarnaOrderId !== null);

        /** @psalm-suppress UndefinedClass (UnitEnum is supported as of PHP 8.1)
         * @var string $orderManagementUrlTemplate
         */
        $orderManagementUrlTemplate = $this->parameterBag->get(
            'north_creation_agency_sylius_klarna_gateway.checkout.read_order',
        );

        $captureUrl = $this->replacePlaceholder($klarnaOrderId, $orderManagementUrlTemplate) . '/captures';

        $klarnaRequestStructure = new KlarnaRequestStructure(
            order: $order,
            taxRateResolver: $this->taxRateResolver,
            shippingChargesProcessor: $this->shippingChargesProcessor,
            parameterBag: $this->parameterBag,
            orderNumberAssigner: $this->orderNumberAssigner,
            entityManager: $this->entityManager,
            type: KlarnaRequestStructure::CAPTURE,
        );

        $payload = $klarnaRequestStructure->toArray();

        try {
            $response = $this->client->request(
                'POST',
                $captureUrl,
                [
                    'headers' => [
                        'Authorization' => $basicAuthString,
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode($payload),
                ],
            );

            $status = $response->getStatusCode();
        } catch (GuzzleException $e) {
            if (str_contains($e->getMessage(), StatusDO::ERROR_CODE_CAPTURE_NOT_ALLOWED)) {
                $status = $this->confirmCaptured($order);
            } else {
                throw new ApiException('Activation of Klarna payment was not successful: ' . $e->getMessage());
            }
        }

        if ($status !== Response::HTTP_CREATED && $status !== Response::HTTP_ACCEPTED) {
            throw new ApiException('Activation of Klarna payment was not successful');
        }

        return $status;
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

    public function supportsPaymentMethod(PaymentMethodInterface $paymentMethod): bool
    {
        $paymentConfig = $paymentMethod->getGatewayConfig();
        assert($paymentConfig instanceof GatewayConfigInterface);

        /** @psalm-suppress DeprecatedMethod */
        $factoryName = $paymentConfig->getFactoryName();

        return str_contains($factoryName, 'klarna_checkout');
    }

    /**
     * @throws SMException
     */
    private function handlePaidOrder(OrderInterface $order): void
    {
        $stateMachine = $this->stateMachineFactory->get($order, OrderPaymentTransitions::GRAPH);
        $stateMachine->apply(OrderPaymentTransitions::TRANSITION_PAY);

        $klarnaPayment = $this->getKlarnaPayment($order);
        if (null === $klarnaPayment) {
            return;
        }

        $stateMachine = $this->stateMachineFactory->get($klarnaPayment, PaymentTransitions::GRAPH);
        $stateMachine->apply(PaymentTransitions::TRANSITION_COMPLETE);
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     */
    private function confirmCaptured(OrderInterface $order): int
    {
        $klarnaPayment = $this->getKlarnaPayment($order);

        assert($klarnaPayment instanceof PaymentInterface);

        $paymentDetails = $klarnaPayment->getDetails();

        /** @var ?string $klarnaOrderId */
        $klarnaOrderId = $paymentDetails['klarna_order_id'] ?? null;
        assert($klarnaOrderId !== null);

        /** @psalm-suppress UndefinedClass (UnitEnum is supported as of PHP 8.1)
         * @var string $orderManagementUrlTemplate
         */
        $orderManagementUrlTemplate = $this->parameterBag->get(
            'north_creation_agency_sylius_klarna_gateway.checkout.read_order',
        );

        $getOrderUrl = $this->replacePlaceholder($klarnaOrderId, $orderManagementUrlTemplate);

        $paymentMethod = $klarnaPayment->getMethod();

        assert($paymentMethod instanceof PaymentMethodInterface);

        $basicAuthString = $this->basicAuthenticationRetriever->getBasicAuthentication($paymentMethod);

        $response = $this->client->request(
            'GET',
            $getOrderUrl,
            [
                'headers' => [
                    'Authorization' => $basicAuthString,
                    'Content-Type' => 'application/json',
                ],
            ],
        );

        /** @var array $data */
        $data = json_decode($response->getBody()->getContents(), true);

        /** @var string $orderStatus */
        $orderStatus = $data['status'] ?? 'Not set';

        if ($orderStatus !== StatusDO::STATUS_CAPTURED) {
            throw new ApiException('Could not capture order. Current status: ' . $orderStatus);
        }

        return StatusDO::PAYMENT_ALREADY_CAPTURED;
    }

    protected function getKlarnaPayment(OrderInterface $order): ?PaymentInterface
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
}
