<?php

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;

interface DataUpdaterInterface
{
    /**
     * Updates customer based on address data from Klarna, not customer data.
     *
     * @throws ApiException
     */
    public function updateCustomer(array $addressData, CustomerInterface $customer): CustomerInterface;

    /**
     * @throws ApiException
     */
    public function updateAddress(array $addressData, AddressInterface $address): AddressInterface;

    /**
     * Asserts that a minimum of array keys are present.
     */
    public function hasCorrectKeys(array $data, array $expectedKeys): bool;
}
