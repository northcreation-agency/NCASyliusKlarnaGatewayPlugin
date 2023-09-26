<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Router\UrlGenerator;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Webmozart\Assert\Assert;

class PayloadDataResolver implements PayloadDataResolverInterface
{
    public function __construct(
        private ContainerInterface $container,
        private ParameterBagInterface $parameterBag,
    ) {
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getMerchantData(PaymentInterface $payment, ?string $hostUrl = null): ?MerchantData
    {
        $method = $payment->getMethod();
        Assert::isInstanceOf($method, PaymentMethodInterface::class);

        /** @var ?array $merchantData */
        $merchantData = $method->getGatewayConfig()?->getConfig()['merchantUrls'] ?? null;
        if (null === $merchantData) {
            return null;
        }

        $order = $payment->getOrder();
        if (null === $order) {
            return null;
        }

        /** @var string|null $pushUrl */
        $pushUrl = $this->getPushUrl($order);

        /** @var string|null $termsUrl */
        $termsUrl = $merchantData['termsUrl'] ?? null;

        /** @var string|null $checkoutUrl */
        $checkoutUrl = $merchantData['checkoutUrl'] ?? null;

        /** @psalm-suppress UndefinedClass (UnitEnum is supported as of PHP 8.1)
        /** @var bool $headlessMode */
        $headlessMode = $this->parameterBag->get('north_creation_agency_sylius_klarna_gateway.checkout.headless') ?? false;

        assert(is_bool($headlessMode));

        /** @var string|null $confirmationHeadfullUrl */
        $confirmationHeadfullUrl = $this->generateUrl(
            'north_creation_agency_sylius_klarna_gateway_confirm',
            ['order_token' => $payment->getOrder()?->getTokenValue() ?? ''],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        /** @var string|null $confirmationUrl */
        $confirmationUrl = $headlessMode ? $merchantData['confirmationUrl'] : $confirmationHeadfullUrl;

        // check if confirmationUrl is relative, and if so, prepend hostUrl
        if (null !== $hostUrl && null !== $confirmationUrl && !str_starts_with($confirmationUrl, 'http')) {
            $confirmationUrl = $hostUrl . $confirmationUrl;

            // check that there is a slash between hostUrl and confirmationUrl
            if (!str_starts_with($confirmationUrl, $hostUrl . '/')) {
                $confirmationUrl = $hostUrl . '/' . $confirmationUrl;
            }
        }

        if (null === $termsUrl || null === $checkoutUrl || null === $confirmationUrl || null === $pushUrl) {
            return null;
        }

        try {
            /** @var RouterInterface $router */
            $router = $this->container->get('router');
            $urlGenerator = new UrlGenerator($router);
            $termsUrl = $urlGenerator->generateAbsoluteURL($termsUrl);
            $checkoutUrl = $urlGenerator->generateAbsoluteURL($checkoutUrl);
            $confirmationUrl = $urlGenerator->generateAbsoluteURL($confirmationUrl, ['tokenValue' => $order->getTokenValue() ?? '']);
        } catch (\Exception $e) {
        }

        return new MerchantData($termsUrl, $checkoutUrl, $confirmationUrl, $pushUrl);
    }

    public function getOptionsData(PaymentInterface $payment): OptionsData
    {
        $method = $payment->getMethod();
        Assert::isInstanceOf($method, PaymentMethodInterface::class);

        /** @var array<string, string> $b2bSettings */
        $b2bSettings = $method->getGatewayConfig()?->getConfig()['b2bSettings'] ?? [];

        return new OptionsData(
            b2bSettings: $b2bSettings,
        );
    }

    protected function generateUrl(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        /** @var RouterInterface $router */
        $router = $this->container->get('router');

        return $router->generate($route, $parameters, $referenceType);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws \Exception
     */
    protected function getPushUrl(OrderInterface $order): string
    {
        $router = $this->container->get('router');
        assert($router instanceof \Symfony\Component\Routing\RouterInterface);

        $pushUrl = $router->generate(
            'north_creation_agency_sylius_klarna_gateway_push',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $tokenValue = $order->getTokenValue();
        if ($tokenValue === null) {
            throw new \Exception('No order token was found.');
        }

        $pushUrl .= '?klarna_order_id={checkout.order.id}';
        $pushUrl .= '&token_value=' . $tokenValue;

        return $pushUrl;
    }

    /**
     * @param array<string, string> $customerData
     */
    public function getCustomerData(array $customerData): CustomerData
    {
        return new CustomerData(
            type: $customerData['type'] ?? 'person',
            organizationRegistrationId: $customerData['organizationRegistrationId'] ?? null,
            vatId: $customerData['vatId'] ?? null,
            gender: $customerData['gender'] ?? null,
            dateOfBirth: $customerData['dateOfBirth'] ?? null,
        );
    }
}
