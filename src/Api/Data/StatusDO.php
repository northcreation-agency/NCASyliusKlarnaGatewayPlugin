<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Data;

use Symfony\Component\HttpFoundation\Response;

class StatusDO
{
    public const PAYMENT_CONFIRMED = Response::HTTP_NO_CONTENT;

    public const PAYMENT_CAPTURED = Response::HTTP_CREATED;

    public const STATUS_CAPTURED = 'CAPTURED';

    public const ERROR_CODE_CAPTURE_NOT_ALLOWED = 'CAPTURE_NOT_ALLOWED';

    public const ERROR_CODE_REFUND_NOT_ALLOWED = 'REFUND_NOT_ALLOWED';

    public const PAYMENT_ALREADY_CAPTURED = Response::HTTP_ACCEPTED;

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
