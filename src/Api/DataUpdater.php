<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use Sylius\Component\Core\Factory\CustomerAfterCheckoutFactory;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Repository\CustomerRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\User\Canonicalizer\Canonicalizer;

class DataUpdater implements DataUpdaterInterface
{
    public const REQUIRED_API_ADDRESS_KEYS = [
        'given_name',
        'family_name',
        'street_address',
        'postal_code',
        'city',
        'phone',
        'country',
    ];

    public const REQUIRED_API_ADDRESS_CUSTOMER_KEYS = [
        'given_name',
        'family_name',
        'email',
        'phone',
    ];

    /**
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerAfterCheckoutFactory $customerFactory
     */
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private FactoryInterface $customerFactory
    ){}

    /**
     * Updates customer based on address data from Klarna, not customer data.
     *
     * @throws ApiException
     */
    public function updateCustomer(array $addressData, CustomerInterface $customer): CustomerInterface
    {
        if (!$this->hasCorrectKeys($addressData, self::REQUIRED_API_ADDRESS_CUSTOMER_KEYS)) {
            throw new ApiException('The data array misses required array keys');
        }

        /**
         * @var string $key
         * @var string $value
         */
        foreach ($addressData as $key => $value) {
            match ($key) {
                'given_name' => $customer->setFirstName($value),
                'family_name' => $customer->setLastName($value),
                'phone' => $customer->setPhoneNumber($value),
                default => $customer
            };
        }

        /** @var ?string $email */
        $email = $addressData['email'] ?? null;
        if ($email === null) {
            return $customer;
        }
        return $this->updateCustomerByEmail($email, $customer);
    }

    protected function updateCustomerByEmail(string $email, CustomerInterface $customer): CustomerInterface
    {
        $canonicalizer = new Canonicalizer();

        $customerOrdersCount = $customer->getOrders()->count();

        if ($customerOrdersCount > 1) {
            /** @var ?CustomerInterface $otherCustomer */
            $otherCustomer = $this->customerRepository->findOneBy(['email' => $email]);
            if ($otherCustomer === null) {
                /** @var CustomerInterface $otherCustomer */
                $otherCustomer = $this->customerFactory->createNew();
                $otherCustomer->setEmail($email);
                $otherCustomer->setFirstName($customer->getFirstName());
                $otherCustomer->setLastName($customer->getLastName());
            }

            $addresses = $customer->getAddresses();
            foreach ($addresses as $address) {
                $otherCustomer->addAddress($address);
            }

            return $otherCustomer;
        }

        $customer->setEmail($email);
        $customer->setEmailCanonical($canonicalizer->canonicalize($email));

        return $customer;
    }

    /**
     * @throws ApiException
     */
    public function updateAddress(array $addressData, AddressInterface $address): AddressInterface
    {
        if (!$this->hasCorrectKeys($addressData, self::REQUIRED_API_ADDRESS_KEYS)) {
            throw new ApiException('The data array misses required array keys: ');
        }

        /**
         * @var string $key
         * @var string $value
         */
        foreach ($addressData as $key => $value) {
            match ($key) {
                'given_name' => $address->setFirstName($value),
                'family_name' => $address->setLastName($value),
                'street_address' => $address->setStreet($value),
                'postal_code' => $address->setPostcode($value),
                'city' => $address->setCity($value),
                'phone' => $address->setPhoneNumber($value),
                'country' => $address->setCountryCode(strtoupper($value)),
                default => $address
            };
        }

        return $address;
    }

    /**
     * Asserts that a minimum of array keys are present.
     */
    public function hasCorrectKeys(array $data, array $expectedKeys): bool
    {
        $keys = array_keys($data);

        $intersection = array_intersect($expectedKeys, $keys);

        return count($intersection) === count($expectedKeys);
    }
}
