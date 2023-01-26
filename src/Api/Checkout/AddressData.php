<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout;

use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\CustomerInterface;

class AddressData
{
    public function __construct(
        private AddressInterface $address
    ){}

    public function toArray(): array
    {
        $customer = $this->address->getCustomer();



        return [
            'given_name' => $this->address->getFirstName(),
            'family_name' => $this->address->getLastName(),
            'organization_name' => $this->address->getCompany(),
            'email' => $customer?->getEmail() ?? '',
            'street_address' => $this->address->getStreet(),
            'postal_code' => $this->address->getPostcode(),
            'city' => $this->address->getCity(),
            'region' => $this->address->getProvinceName(),
            'country' => strtolower($this->address->getCountryCode()),
        ];
    }

}
