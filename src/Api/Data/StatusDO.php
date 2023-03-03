<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Data;

class StatusDO
{
    public function __construct(
        private int $status,
        private ?string $message,
        private ?string $errorMessage,
    ) {
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
