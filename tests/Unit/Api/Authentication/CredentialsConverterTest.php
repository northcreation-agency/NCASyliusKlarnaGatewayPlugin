<?php

declare(strict_types=1);

namespace Tests\NorthCreationAgency\SyliusKlarnaGatewayPlugin\Unit\Api\Authentication;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Authentication\CredentialsConverter;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class CredentialsConverterTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function testToBase64(): void
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnOnConsecutiveCalls('username', 'password');
        $credentialsConverter = new CredentialsConverter($parameterBag);
        Assert::assertSame('dXNlcm5hbWU6cGFzc3dvcmQ=', $credentialsConverter->toBase64());
    }
}
