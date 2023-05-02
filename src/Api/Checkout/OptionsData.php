<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout;

class OptionsData
{
    public function __construct(
        /** @var array<string, string> $b2bSettings */
        private array $b2bSettings = [],
    ) {
    }

    public function toArray(): array
    {
        $options = [];

        if (count($this->b2bSettings) > 0) {
            $options = array_merge($this->handleB2Bsettings($options, $this->b2bSettings));
        }

        return $options;
    }

    /**
     * @param array<string, string> $options
     * @param array<string, string> $b2bSettings
     */
    protected function handleB2Bsettings(array $options, array $b2bSettings): array
    {
        $supportB2B = $b2bSettings['supportB2B'] ?? false;
        if ($supportB2B !== false) {
            $options['allowed_customer_types'] = ['person', 'organization'];
        }

        return $options;
    }
}
