<?php

declare(strict_types=1);

namespace Tests\NorthCreationAgency\SyliusKlarnaGatewayPlugin\Unit\Api\Checkout;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\AddressData;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\Customer;

class AddressDataTest extends TestCase
{
    private AddressData $addressData;

    public function setUp(): void
    {
        $customer = new Customer();
        $customer->setEmail('jane.doe@example.com');
        $customer->setFirstName('Jane');
        $customer->setLastName('Doe');
        $customer->setPhoneNumber('+46701234567');

        $address = new Address();
        $address->setFirstName($customer->getFirstName());
        $address->setLastName($customer->getLastName());
        $address->setPhoneNumber($customer->getPhoneNumber());
        $address->setStreet('47 Poynings Road');
        $address->setPostcode('N19 5LH');
        $address->setCity('London');
        $address->setCountryCode('GB');
        $address->setCustomer($customer);

        $this->addressData = new AddressData($address);
    }

    public function testAddressIsCorrectlyConvertedToArray(): void
    {
        $expected = [
            'given_name' => 'Jane',
            'family_name' => 'Doe',
            'organization_name' => null,
            'email' => 'jane.doe@example.com',
            'street_address' => '47 Poynings Road',
            'postal_code' => 'N19 5LH',
            'city' => 'London',
            'region' => null,
            'country' => 'gb',
            'phone' => '+46701234567'
        ];

        $actual = $this->addressData->toArray();

        self::assertEquals($expected, $actual);
    }
}
