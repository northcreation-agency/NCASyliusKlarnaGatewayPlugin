<?php

declare(strict_types=1);

namespace Tests\NorthCreationAgency\SyliusKlarnaGatewayPlugin\Unit\Api\Authentication;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\BasicAuthenticationRetriever;
use Payum\Core\Security\CypherInterface;

class BasicAuthenticationRetrieverTest extends \PHPUnit\Framework\TestCase
{
    private BasicAuthenticationRetriever $basicAuthenticationRetriever;

    protected function setUp(): void
    {
        $cypher = $this->createMock(CypherInterface::class);
        $this->basicAuthenticationRetriever = new BasicAuthenticationRetriever($cypher);
    }

    public function testFieldHasStringValueReturnsTrueForExisitingCredentials(): void
    {
        $config = [
            'api_username' => 'username',
            'api_password' => 'password',
        ];

        $userNameExists = $this->basicAuthenticationRetriever->fieldHasStringValue('api_username', $config);
        $passwordExists = $this->basicAuthenticationRetriever->fieldHasStringValue('api_password', $config);

        self::assertTrue($userNameExists && $passwordExists);
    }
}
