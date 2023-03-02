<?php

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;

class DataUpdater
{
    const REQUIRED_API_ADDRESS_KEYS = [
        'given_name',
        'family_name',
        'street_address',
        'postal_code',
        'city',
        'phone',
        'country'
    ];

    public function updateCustomer(array $data, CustomerInterface $customer): CustomerInterface
    {
        return $customer;
    }

    public function updateAddress(array $data, AddressInterface $address): AddressInterface
    {
        if (!$this->hasCorrectKeys($data, self::REQUIRED_API_ADDRESS_KEYS)) {
            throw new ApiException('The data array misses required array keys: ');
        }

        foreach ($data as $key => $value) {
            match($key) {
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
     * @param array $data
     * @return bool
     */
    public function hasCorrectKeys(array $data, array $expectedKeys): bool
    {
        $keys = array_keys($data);

        $intersection = array_intersect($expectedKeys, $keys);

        return count($intersection) === count($expectedKeys);
    }

}
