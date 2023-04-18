<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\StateMachine;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\BasicAuthenticationRetrieverInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\KlarnaRequestStructure;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use Payum\Core\Model\GatewayConfigInterface;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;

class ActivatePayment implements ActivatePaymentInterface
{
    public function __construct(
        private ClientInterface $client,
        private TaxRateResolverInterface $taxRateResolver,
        private OrderProcessorInterface $shippingChargesProcessor,
        private CalculatorInterface $taxCalculator,
        private ParameterBagInterface $parameterBag,
        private BasicAuthenticationRetrieverInterface $basicAuthenticationRetriever,
        private OrderNumberAssignerInterface $orderNumberAssigner,
    ) {
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     */
    public function activate(OrderInterface $order): void
    {
        $payment = $order->getLastPayment();

        assert($payment instanceof PaymentInterface);

        $this->sendCaptureRequest($payment);
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     */
    public function sendCaptureRequest(SyliusPaymentInterface $payment): void
    {
        $paymentMethod = $payment->getMethod();
        assert($paymentMethod instanceof PaymentMethodInterface);
        if (!$this->supportsPaymentMethod($paymentMethod)) {
            return;
        }

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
            taxCalculator: $this->taxCalculator,
            parameterBag: $this->parameterBag,
            orderNumberAssigner: $this->orderNumberAssigner,
            type: KlarnaRequestStructure::CAPTURE,
        );

        $payload = $klarnaRequestStructure->toArray();

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
        if ($status !== Response::HTTP_CREATED) {
            throw new ApiException('Activation of Klarna payment was not successful');
        }
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
}
