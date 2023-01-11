<?php

declare(strict_types=1);

namespace AndersBjorkland\SyliusKlarnaGatewayPlugin\Api\Checkout;

class MerchantData
{


    public function __construct(
        private string $termsUrl,
        private string $checkoutUrl,
        private string $confirmationUrl,
        private string $pushUrl,
    ){}

    public function toArray(): array
    {
        return [
            'terms' => $this->termsUrl,
            'checkout' => $this->checkoutUrl,
            'confirmation' => $this->confirmationUrl,
            'push' => $this->pushUrl,
        ];
    }
}
