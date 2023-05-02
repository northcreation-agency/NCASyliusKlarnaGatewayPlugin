<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout;

class CustomerData
{
    public function __construct(
        private string $type = 'person',
        private ?string $organizationRegistrationId = null,
        private ?string $vatId = null,
        private ?string $gender = null,
        private ?string $dateOfBirth = null,
    ) {
    }

    public function toArray(): array
    {
        $customerData = [
            'type' => $this->type,
        ];

        if ($this->organizationRegistrationId !== null) {
            $customerData['organization_registration_id'] = $this->organizationRegistrationId;
        }

        if ($this->vatId !== null) {
            $customerData['vat_id'] = $this->vatId;
        }

        if ($this->gender !== null) {
            $customerData['gender'] = $this->gender;
        }

        if ($this->dateOfBirth !== null) {
            $customerData['date_of_birth'] = $this->dateOfBirth;
        }

        return $customerData;
    }
}
