<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;

class B2BType extends \Symfony\Component\Form\AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('supportB2B', CheckboxType::class, [
                'label' => 'nca_sylius_klarna_gateway_plugin.form.gateway_configuration.support_b2b.label',
                'help' => 'nca_sylius_klarna_gateway_plugin.form.gateway_configuration.support_b2b.help',
                'required' => false,
            ])
            ->add('b2bTermsUrl', UrlType::class, [
                'label' => 'nca_sylius_klarna_gateway_plugin.form.gateway_configuration.b2bTermsUrl.label',
                'help' => 'nca_sylius_klarna_gateway_plugin.form.gateway_configuration.b2bTermsUrl.help',
                'required' => false,
            ])
        ;
    }
}
