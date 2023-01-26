<?php

declare(strict_types=1);

namespace Tests\NorthCreationAgency\SyliusKlarnaGatewayPlugin\Retriever;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Retriever\AllowedCountriesRetriever;
use Sylius\Component\Addressing\Model\Country;
use Sylius\Component\Core\Model\Channel;

class AllowedCountriesRetrieverTest extends \PHPUnit\Framework\TestCase
{
    public function testCountryCodesCorrectlyRetrievedFromChannel(): void
    {
        $gb = new Country();
        $gb->setCode('GB');

        $us = new Country();
        $us->setCode('US');

        $channel = new Channel();
        $channel->addCountry($gb);
        $channel->addCountry($us);

        $expected = ['GB', 'US'];

        $actual = AllowedCountriesRetriever::getCountryCodes($channel);

        self::assertEquals($expected, $actual);
    }
}
