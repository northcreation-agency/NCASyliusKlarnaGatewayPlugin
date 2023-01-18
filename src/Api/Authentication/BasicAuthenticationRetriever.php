<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication;

use Payum\Core\Security\CypherInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

class BasicAuthenticationRetriever implements BasicAuthenticationRetrieverInterface
{
    public function __construct(
        private CypherInterface $cypher,
    ) {
    }

    /**
     * @return string Base64 encoded string with leading 'Basic '
     *
     * @throws \Exception
     */
    public function getBasicAuthentication(PaymentMethodInterface $paymentMethod): string
    {
        $config = $this->getGatewayConfig($paymentMethod);

        if (
            !$this->fieldHasStringValue('api_username', $config) ||
            !$this->fieldHasStringValue('api_password', $config)
        ) {
            throw new \Exception('Missing API credentials: ' . json_encode($config));
        }

        $apiUsername = $this->cypher->decrypt((string) $config['api_username']);
        $apiPassword = $this->cypher->decrypt((string) $config['api_password']);

        return 'Basic ' . CredentialsConverter::toBase64(
            $apiUsername,
            $apiPassword,
        );
    }

    protected function getGatewayConfig(PaymentMethodInterface $paymentMethod): array
    {
        return $paymentMethod->getGatewayConfig()?->getConfig() ?? [];
    }

    protected function decrypt(string $encryptedField): string
    {
        return $this->cypher->decrypt($encryptedField);
    }

    public function fieldHasStringValue(string $field, array $data): bool
    {
        if (!array_key_exists($field, $data)) {
            return false;
        }

        if (!is_string($data[$field])) {
            return false;
        }

        return strlen($data[$field]) > 0;
    }
}
