<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Retriever;

use Sylius\Component\Core\Model\Channel;

class AllowedCountriesRetriever
{
    /**
     * Retrieves country codes associated with a channel
     *
     * @return string[]
     */
    public static function getCountryCodes(Channel $channel): array
    {
        $countries = $channel->getCountries();
        $countryCodes = [];

        foreach ($countries as $country) {
            $code = $country->getCode();
            if (is_string($code)) {
                $countryCodes[] = $code;
            }
        }

        return $countryCodes;
    }
}
