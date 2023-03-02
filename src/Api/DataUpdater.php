<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\User\Canonicalizer\Canonicalizer;

class DataUpdater
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
                'email' => $this->updateCustomerEmail($value, $customer),
                'phone' => $customer->setPhoneNumber($value),
                default => $customer
            };
        }

        return $customer;
    }

    protected function updateCustomerEmail(string $email, CustomerInterface $customer): CustomerInterface
    {
        $canonicalizer = new Canonicalizer();

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
