<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Payum\Action;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\BasicAuthenticationRetrieverInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\KlarnaRequestStructure;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\MerchantData;
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

class CaptureAction implements ActionInterface, ApiAwareInterface
{
    private ?KlarnaApi $api;

    public function __construct(
        private ClientInterface $client,
        private ParameterBagInterface $parameterBag,
        private TaxRateResolverInterface $taxRateResolver,
        private OrderProcessorInterface $shippingChargesProcessor,
        private BasicAuthenticationRetrieverInterface $basicAuthenticationRetriever,
        private CalculatorInterface $taxCalculator,
    ) {
        $this->api = null;
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        assert($request instanceof Capture);

        /** @var SyliusPaymentInterface $payment */
        $payment = $request->getModel();

        $order = $payment->getOrder();
        assert($order instanceof OrderInterface);

        $requestStructure = new KlarnaRequestStructure(
            order: $order,
            merchantData:  new MerchantData('example.com', 'example.com', 'example.com', 'example.com'),
            taxRateResolver: $this->taxRateResolver,
            shippingChargesProcessor: $this->shippingChargesProcessor,
            taxCalculator: $this->taxCalculator,
        );

        /** @psalm-suppress UndefinedClass (UnitEnum is supported as of PHP 8.1) */
        $klarnaUri = $this->parameterBag->get('north_creation_agency_sylius_klarna_gateway.checkout.uri');

        assert(is_string($klarnaUri));

        $method = $payment->getMethod();

        assert($method instanceof PaymentMethodInterface);

        $basicAuthentication = $this->basicAuthenticationRetriever->getBasicAuthentication($method);
        $response = null;

        try {
            $requestStructureArray = $requestStructure->toArray();

            $response = $this->client->request(
                'POST',
                $klarnaUri,
                [
                    'headers' => [
                        'Authorization' => $basicAuthentication,
                        'Content-Type' => 'application/json',
                    ],
                    'body' => json_encode($requestStructureArray),
                ],
            );
        } catch (RequestException $e) {
            $response = $e->getResponse();
        } catch (\Exception $e) {
            $response = 500;
        } finally {
            if ($response instanceof \Psr\Http\Message\ResponseInterface) {
                $responseCode = $response->getStatusCode();
            } else {
                $responseCode = 500;
            }

            $payment->setDetails(['status' => $responseCode]);
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
}
