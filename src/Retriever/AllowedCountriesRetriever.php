<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Retriever;

use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Core\Model\Channel;

class AllowedCountriesRetriever
{

    /**
     * Retrieves country codes associated with a channel
     * @param Channel $channel
     * @return string[]
     */
    public static function getCountryCodes(Channel $channel): array
    {
        $countries = $channel->getCountries();
        $countryCodes = [];

        /** @var CountryInterface $country */
        foreach ($countries as $country) {
            $code = $country->getCode();
            if (is_string($code)) {
                $countryCodes[] = $code;
            }
        }

        return $countryCodes;
    }
}
