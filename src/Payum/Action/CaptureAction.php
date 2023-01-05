<?php

declare(strict_types=1);

namespace AndersBjorkland\SyliusKlarnaGatewayPlugin\Payum\Action;

use AndersBjorkland\SyliusKlarnaGatewayPlugin\Payum\ValueObject\KlarnaApi;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Request\Capture;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CaptureAction implements ActionInterface, ApiAwareInterface
{

    private KlarnaApi $api;

    public function __construct(
        private Client $client,
        private ParameterBagInterface $parameterBag
    ){}

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        assert($request instanceof Capture);

        /** @var SyliusPaymentInterface $payment */
        $payment = $request->getModel();

        $apiKey = $this->api->getApiKey();
        $klarnaUri = $this->parameterBag->get('sylius_klarna_gateway.checkout.uri');

        assert(is_string($klarnaUri));

        try {
            $response = $this->client->request(
                'POST',
                $klarnaUri,
                [
                    'body' => json_encode([
                        'price' => $payment->getAmount(),
                        'currency' => $payment->getCurrencyCode(),
                        'api_key' => $apiKey
                    ]),
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
