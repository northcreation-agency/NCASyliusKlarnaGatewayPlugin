<?php

declare(strict_types=1);

namespace AndersBjorkland\SyliusKlarnaGatewayPlugin\Form\Type;

use Payum\Core\Security\CypherInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class KlarnaCheckoutGatewayConfigurationType extends AbstractType
{
    public const API_USERNAME = 'api_username';

    public const API_PASSWORD = 'api_password';

    /** @var string[] */
    private array $encryptedFields = [
        self::API_USERNAME,
        self::API_PASSWORD,
    ];

    public function __construct(
        private CypherInterface $cypher,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // decrypt the gateway config
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
                $data = $event->getData();

                foreach ($this->encryptedFields as $encryptedField) {
                    if ($this->isDecipherable($encryptedField, $data)) {
                        assert(is_array($data));
                        assert(is_string($data[$encryptedField]));
                        $data[$encryptedField] = $this->cypher->decrypt($data[$encryptedField]);
                    }
                }

                $event->setData($data);
            })

            // encrypt the gateway config
            ->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
                $data = $event->getData();

                foreach ($this->encryptedFields as $encryptedField) {
                    if ($this->isEncipherable($encryptedField, $data)) {
                        assert(is_array($data));
                        assert(is_string($data[$encryptedField]));
                        $data[$encryptedField] = $this->cypher->encrypt($data[$encryptedField]);
                    }
                }

                $event->setData($data);
            })

            // add the form fields
            ->add('api_username', TextType::class, [
                'label' => 'ncagency.form.gateway_configuration.api_user.label',
                'help' => 'ncagency.form.gateway_configuration.api_user.help',
            ])
            ->add('api_password', PasswordType::class, [
                'label' => 'ncagency.form.gateway_configuration.api_password.label',
                'help' => 'ncagency.form.gateway_configuration.api_password.help',
            ])
        ;
    }

    protected function isDecipherable(string $key, mixed $data): bool
    {
        if (!is_array($data)) {
            return false;
        }

        if (!array_key_exists($key, $data)) {
            return false;
        }

        if (!is_string($data[$key])) {
            return false;
        }

        return $this->isHex($data[$key]);
    }

    protected function isEncipherable(string $key, mixed $data): bool
    {
        return is_array($data) && array_key_exists($key, $data);
    }

    public function isHex(string $string): bool
    {
        return ctype_xdigit($string);
    }
}
