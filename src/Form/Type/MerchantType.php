<?php

declare(strict_types=1);

namespace AndersBjorkland\SyliusKlarnaGatewayPlugin\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class MerchantType extends \Symfony\Component\Form\AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('termsUrl', TextType::class, [
                'label' => 'nca_sylius_klarna_gateway_plugin.ui.terms_url',
                'required' => false,
            ])
            ->add('checkoutUrl', TextType::class, [
                'label' => 'nca_sylius_klarna_gateway_plugin.ui.checkout_url',
                'required' => false,
            ])
            ->add('confirmationUrl', TextType::class, [
                'label' => 'nca_sylius_klarna_gateway_plugin.ui.confirmation_url',
                'required' => false,
            ])
            ->add('pushUrl', TextType::class, [
                'label' => 'nca_sylius_klarna_gateway_plugin.ui.push_url',
                'required' => false,
            ])
        ;
    }
}
