<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Form\Type;

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
                'label' => 'nca_sylius_klarna_gateway_plugin.ui.terms_url.label',
                'required' => true,
            ])
            ->add('checkoutUrl', TextType::class, [
                'label' => 'nca_sylius_klarna_gateway_plugin.ui.checkout_url.label',
                'required' => true,
            ])
            ->add('confirmationUrl', TextType::class, [
                'label' => 'nca_sylius_klarna_gateway_plugin.ui.confirmation_url.label',
                'help' => 'nca_sylius_klarna_gateway_plugin.ui.confirmation_url.help',
                'required' => true,
            ])
            ->add('pushUrl', TextType::class, [
                'label' => 'nca_sylius_klarna_gateway_plugin.ui.push_url.label',
                'help' => 'nca_sylius_klarna_gateway_plugin.ui.push_url.help',
                'required' => true,
            ])
        ;
    }
}
