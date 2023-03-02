<?php

namespace Tests\NorthCreationAgency\SyliusKlarnaGatewayPlugin\Unit\Api;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\DataUpdater;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\Address;
use Sylius\Component\Core\Model\AddressInterface;
use Symfony\Component\VarDumper\Cloner\Data;

class DataUpdaterTest extends TestCase
{
    private DataUpdater $dataUpdater;
    private array $exampleAddressData;

    protected function setUp(): void
    {
        $this->dataUpdater = new DataUpdater();
        $this->exampleAddressData = [
            'given_name'=> 'Jane',
            'family_name'=> 'Doe',
            'email'=> 'jane.doe@example.com',
            'title'=> 'Ms',
            'street_address'=> '47 Poynings Road',
            'postal_code'=> 'N19 5LH',
            'city'=> 'London',
            'phone'=> '+46 70 123 45 67',
            'country'=> 'gb'
        ];
    }

    public function testAssertFalseAddressStructure(): void
    {
        $exampleArray = [
            'a' => 1,
            'b' => 2,
            'c' => 3,
            'd' => 4,
            'e' => 5,
            'f' => 6,
            'g' => 7
        ];

        $actualAssertion = $this->dataUpdater->hasCorrectKeys($exampleArray, DataUpdater::REQUIRED_API_ADDRESS_KEYS);

        self::assertFalse($actualAssertion);
    }

    public function testAssertTrueAddressStructure(): void
    {
        $correctArray = $this->exampleAddressData;

        $actualAssertion = $this->dataUpdater->hasCorrectKeys($correctArray, DataUpdater::REQUIRED_API_ADDRESS_KEYS);

        self::assertTrue($actualAssertion);
    }

    public function testUpdatedKlarnaAddressUpdatesSylius(): void
    {
        $klarnaAddress = $this->exampleAddressData;
        $address = $this->getBillingAddress();

        $address = $this->dataUpdater->updateAddress($klarnaAddress, $address);

        $expectedPostcode = 'N19 5LH';
        $actualPostcode = $address->getPostcode();

        $this->assertEquals($expectedPostcode, $actualPostcode);

    }
    public function testDifferentKlarnaAddressUpdatesSylius(): void
    {
        $filePath = __DIR__ . '/rawKlarnaOrderData.txt';
        $rawDataContent = file_get_contents($filePath);

        assert(is_string($rawDataContent));

        /** @var array $rawData */
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
