<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Customer\Model\CustomerInterface;

class AddressData
{
    public function __construct(
        private AddressInterface $address,
        private ?CustomerInterface $customer = null
    ){
        if ($customer === null) {
            $this->customer = $this->address->getCustomer();
        }
    }

    public function toArray(): array
    {
        $customer = $this->customer;

        $email = $customer?->getEmail();

        $phone = $this->address->getPhoneNumber() ?? $customer?->getPhoneNumber() ?? '';

        $countryCode = $this->address->getCountryCode() ?? '';


        return [
            'given_name' => $this->address->getFirstName(),
            'family_name' => $this->address->getLastName(),
            'organization_name' => $this->address->getCompany(),
            'email' => $email,
            'street_address' => $this->address->getStreet(),
            'postal_code' => $this->address->getPostcode(),
            'city' => $this->address->getCity(),
            'region' => $this->address->getProvinceName(),
            'country' => strtolower($countryCode),
            'phone' => $phone
        ];
    }

}
