<?php

declare(strict_types=1);

namespace Tests\AndersBjorkland\SyliusKlarnaGatewayPlugin\Unit\Form\Type;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;

class MerchantTypeTest extends \PHPUnit\Framework\TestCase
{

    public function testHasCorrectFields(): void
    {
        $form = $this->createForm();
        self::assertTrue($form->has('termsUrl'));
        self::assertTrue($form->has('checkoutUrl'));
        self::assertTrue($form->has('confirmationUrl'));
        self::assertTrue($form->has('pushUrl'));
    }

    /**
     * @return FormInterface
     */
    private function createForm(): \Symfony\Component\Form\FormInterface
    {
        return Forms::createFormFactoryBuilder()
            ->getFormFactory()
            ->createBuilder(\AndersBjorkland\SyliusKlarnaGatewayPlugin\Form\Type\MerchantType::class)
            ->getForm();
    }
}