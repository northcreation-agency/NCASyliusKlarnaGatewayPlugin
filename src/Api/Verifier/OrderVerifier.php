<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Verifier;

use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\BasicAuthenticationRetrieverInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Data\StatusDO;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\DataUpdaterInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\OrderManagementInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Retriever\KlarnaPaymentRetriever;
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
        private OrderManagementInterface $orderManagement,
        private DataUpdaterInterface $dataUpdater,
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
        $klarnaData = $this->orderManagement->fetchOrderDataFromKlarna($order);

        try {
            $this->updateFromKlarna($klarnaData, $order);
        } catch (ApiException $e) {
        }

        $this->entityManager->flush();
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
            $updatedCustomer = $this->updateCustomer($billingAddressData, $order);
            $billingAddress = $order->getBillingAddress();
            if ($billingAddress !== null) {
                $this->updateAddress($billingAddressData, $billingAddress);
            }

            if ($customer->getEmail() !== $updatedCustomer->getEmail()) {
                $order->setCustomer($updatedCustomer);
                $this->entityManager->persist($order);
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

        /** @var ?array $klarnaCustomerData */
        $klarnaCustomerData = $data['customer'] ?? null;

        $paymentRetriever = new KlarnaPaymentRetriever();
        $payment = $paymentRetriever->retrieveFromOrder($order);

        if ($klarnaCustomerData !== null && count($klarnaCustomerData) > 0) {
            if ($payment !== null) {
                $this->addPaymentDetails($payment, 'customer', $klarnaCustomerData);
            }
        }

        /** @var ?string $klarnaOrderReference */
        $klarnaOrderReference = $data['klarna_reference'] ?? null;

        if ($klarnaOrderReference !== null && $payment !== null) {
            $this->addPaymentDetails($payment, 'klarna_order_reference', $klarnaOrderReference);
        }
    }

    /**
     * @throws ApiException
     */
    private function updateCustomer(array $addressData, OrderInterface $order): CustomerInterface
    {
        $customer = $order->getCustomer();
        assert($customer instanceof CustomerInterface);
        $customer = $this->dataUpdater->updateCustomer($addressData, $customer);

        $this->entityManager->persist($customer);
        return $customer;
    }

    /**
     * @throws ApiException
     */
    private function updateAddress(array $data, AddressInterface $address): void
    {
        $address = $this->dataUpdater->updateAddress($data, $address);

        $this->entityManager->persist($address);
    }

    private function addPaymentDetails(PaymentInterface $payment, string $detailsKey, null|array|string $klarnaCustomerData): void
    {
        $details = $payment->getDetails();

        $details[$detailsKey] = $klarnaCustomerData;

        $payment->setDetails($details);
    }
}
