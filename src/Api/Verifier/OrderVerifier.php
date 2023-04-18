<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Verifier;

use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\BasicAuthenticationRetrieverInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Data\StatusDO;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\DataUpdater;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\Customer;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class OrderVerifier implements OrderVerifierInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BasicAuthenticationRetrieverInterface $basicAuthenticationRetriever,
        private ParameterBagInterface $parameterBag,
        private ClientInterface $client,
    ) {
    }

    public function verify(OrderInterface $order): StatusDO
    {
        $payment = $order->getLastPayment();
        assert($payment instanceof PaymentInterface);

        $method = $payment->getMethod();
        assert($method instanceof PaymentMethodInterface);

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

        try {
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
        }

        return new StatusDO($status);
    }

    public function update(OrderInterface $order): void
    {
        $klarnaData = $this->fetchOrderDataFromKlarna($order);

        try {
            $this->updateFromKlarna($klarnaData, $order);
        } catch (ApiException $e) {
        }

        $this->entityManager->flush();
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
        /** @var ?string $email */
        $email = $data['billing_address']['email'] ?? $data['shipping_address']['email'] ?? null;

        $customer = $order->getCustomer();
        assert($customer instanceof CustomerInterface);

        if ($email !== null && $email !== $customer->getEmail()) {

            /** @var CustomerRepositoryInterface $customerRepository */
            $customerRepository = $this->entityManager->getRepository(Customer::class);

            /** @var ?CustomerInterface $customerAlternative */
            $customerAlternative = $customerRepository->findOneBy(['email' => $email]);

            if ($customerAlternative !== null) {
                $order->setCustomer($customerAlternative);
            }
        }

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

    /**
     * @throws ApiException
     */
    private function updateCustomer(array $addressData, OrderInterface $order): void
    {
        $dataUpdater = new DataUpdater();
        $customer = $order->getCustomer();
        assert($customer instanceof CustomerInterface);
        $customer = $dataUpdater->updateCustomer($addressData, $customer);

        $this->entityManager->persist($customer);
    }

    /**
     * @throws ApiException
     */
    private function updateAddress(array $data, AddressInterface $address): void
    {
        $dataUpdater = new DataUpdater();
        $address = $dataUpdater->updateAddress($data, $address);

        $this->entityManager->persist($address);
    }
}
