<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Payum\Action;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\BasicAuthenticationRetrieverInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Payum\ValueObject\KlarnaApi;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Request\Capture;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CaptureAction implements ActionInterface, ApiAwareInterface
{
    private ?KlarnaApi $api;

    public function __construct(
        private ClientInterface $client,
        private ParameterBagInterface $parameterBag,
        private BasicAuthenticationRetrieverInterface $basicAuthenticationRetriever,
    ) {
        $this->api = null;
    }

    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        assert($request instanceof Capture);

        /** @var SyliusPaymentInterface $payment */
        $payment = $request->getModel();
        $paymentDetails = $payment->getDetails();

        $order = $payment->getOrder();
        assert($order instanceof OrderInterface);

        $status = 200;

        try {
            $method = $payment->getMethod();
            assert($method instanceof PaymentMethodInterface);
            $basicAuthString = $this->basicAuthenticationRetriever->getBasicAuthentication($method);

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
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $status = $response?->getStatusCode() ?? 404;
        } catch (\Exception $e) {
            $status = 404;
        } finally {
            $paymentDetails['status'] = $status;
            $payment->setDetails($paymentDetails);
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
