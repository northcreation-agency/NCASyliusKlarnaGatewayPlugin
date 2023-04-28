<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout;

class OptionsData
{
    public function __construct(
        private array $b2bSettings = []
    ){}

    public function toArray(): array
    {
        $options = [];

        if (count($this->b2bSettings) > 0) {
            $options = array_merge($this->handleB2Bsettings($options, $this->b2bSettings));
        }

        return $options;
    }

    protected function handleB2Bsettings(array $options, array $b2bSettings): array
    {
        $supportB2B = $b2bSettings['supportB2B'] ?? false;
        if ($supportB2B) {
            $options['allowed_customer_types'] = ['person', 'organization'];
        }
        return $options;
    }
}
