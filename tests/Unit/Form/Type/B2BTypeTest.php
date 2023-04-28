<?php

declare(strict_types=1);

namespace Tests\NorthCreationAgency\SyliusKlarnaGatewayPlugin\Unit\Form\Type;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Form\Type\B2BType;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Form\Type\MerchantType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;

class B2BTypeTest extends \PHPUnit\Framework\TestCase
{

    public function testHasCorrectFields(): void
    {
        $form = $this->createForm();
        self::assertTrue($form->has('supportB2B'));
        self::assertTrue($form->has('b2bTermsUrl'));
    }

    /**
     * @return FormInterface
     */
    private function createForm(): \Symfony\Component\Form\FormInterface
    {
        return Forms::createFormFactoryBuilder()
            ->getFormFactory()
            ->createBuilder(B2BType::class)
            ->getForm();
    }
}
