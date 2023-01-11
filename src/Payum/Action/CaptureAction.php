<?php

declare(strict_types=1);

namespace AndersBjorkland\SyliusKlarnaGatewayPlugin\Payum\Action;

use AndersBjorkland\SyliusKlarnaGatewayPlugin\Api\Checkout\KlarnaRequestStructure;
use AndersBjorkland\SyliusKlarnaGatewayPlugin\Api\MerchantData;
use AndersBjorkland\SyliusKlarnaGatewayPlugin\Payum\ValueObject\KlarnaApi;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Request\Capture;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CaptureAction implements ActionInterface, ApiAwareInterface
{

    private KlarnaApi $api;

    public function __construct(
        private Client $client,
        private ParameterBagInterface $parameterBag,
        private TaxRateResolverInterface $taxRateResolver,
    ){}

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
            taxRateResolver: $this->taxRateResolver
        );

        try {
            $requestStructureArray = $requestStructure->toArray();
        } catch (\Exception $e) {
        }

        $apiKey = $this->api->getApiKey();
        $klarnaUri = $this->parameterBag->get('sylius_klarna_gateway.checkout.uri');

        assert(is_string($klarnaUri));

        try {
            $response = $this->client->request(
                'POST',
                $klarnaUri,
                [
                    'body' => json_encode($requestStructureArray),
                ]
            );
        } catch (RequestException $e) {
            $response = $e->getResponse();
        } catch (\Exception $e) {
            $response = 500;
        } finally {
            $payment->setDetails(['status' => $response?->getStatusCode() ?? 400]);
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
}