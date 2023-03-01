<?php

namespace Tests\NorthCreationAgency\SyliusKlarnaGatewayPlugin\Unit\Api;

use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\AddressInterface;

class DataUpdaterTest extends TestCase
{
    public function testDifferentKlarnaAddressUpdatesSylius(): void
    {
        $filePath = __DIR__ . '/rawKlarnaOrderData.txt';
        $rawDataContent = file_get_contents($filePath);

        $rawData = json_decode($rawDataContent, true);

        $address = $this->getBillingAddress();

        self::assertArrayHasKey('billing_address', $rawData);
    }

    private function getBillingAddress(): AddressInterface
    {
        $address = new Address();
        $address->setStreet('23 Burning St');
        $address->setPostcode('N19 1LH');
        $address->setCity('London');
        $address->setCountryCode('GB');
        $address->setPhoneNumber('+46701234569');

        $address->setFirstName('Jane');
        $address->setLastName('Doe');

        return $address;
    }

}
