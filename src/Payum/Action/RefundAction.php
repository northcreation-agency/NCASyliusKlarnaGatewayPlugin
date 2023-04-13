<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Payum\Action;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\BasicAuthenticationRetrieverInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\KlarnaRequestStructure;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Payum\ValueObject\KlarnaApi;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Request\Capture;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Taxation\Calculator\CalculatorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;

class RefundAction implements ActionInterface, ApiAwareInterface
{
    private ?KlarnaApi $api;

    public function __construct(
        private ClientInterface $client,
        private TaxRateResolverInterface $taxRateResolver,
        private OrderProcessorInterface $shippingChargesProcessor,
        private CalculatorInterface $taxCalculator,
        private ParameterBagInterface $parameterBag,
        private BasicAuthenticationRetrieverInterface $basicAuthenticationRetriever,
    ) {
        $this->api = null;
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     */
    public function refund(SyliusPaymentInterface $payment): void
    {
        $this->sendRefundRequest($payment);
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        assert($request instanceof Capture);

        /** @var SyliusPaymentInterface $payment */
        $payment = $request->getModel();

        $this->sendRefundRequest($payment);
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     */
    public function sendRefundRequest(SyliusPaymentInterface $payment): void
    {
        $paymentDetails = $payment->getDetails();

        $order = $payment->getOrder();
        assert($order instanceof OrderInterface);

        $paymentMethod = $payment->getMethod();

        assert($paymentMethod instanceof PaymentMethodInterface);
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

        $refundUrl = $this->replacePlaceholder($klarnaOrderId, $orderManagementUrlTemplate) . '/refunds';

        $klarnaRequestStructure = new KlarnaRequestStructure(
            order: $order,
            taxRateResolver: $this->taxRateResolver,
            shippingChargesProcessor: $this->shippingChargesProcessor,
            taxCalculator: $this->taxCalculator,
            parameterBag: $this->parameterBag,
            type: KlarnaRequestStructure::REFUND,
        );

        $payload = $klarnaRequestStructure->toArray();

        $response = $this->client->request(
            'POST',
            $refundUrl,
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
            throw new ApiException('Refund was not created with Klarna');
        }
    }

    public function supports($request): bool
    {
        return $request instanceof Capture && $request->getModel() instanceof SyliusPaymentInterface;
    }

    public function setApi($api): void
    {
        if (!$api instanceof KlarnaApi) {
            throw new UnsupportedApiException('Expected an instance of ' . KlarnaApi::class);
        }

        $this->api = $api;
    }

    public function getApi(): ?KlarnaApi
    {
        return $this->api;
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
