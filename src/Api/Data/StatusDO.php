<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Data;

use Symfony\Component\HttpFoundation\Response;

class StatusDO
{
    public const PAYMENT_CONFIRMED = Response::HTTP_NO_CONTENT;

    public function __construct(
        private int $status,
        private ?string $message = null,
        private ?string $errorMessage = null,
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
