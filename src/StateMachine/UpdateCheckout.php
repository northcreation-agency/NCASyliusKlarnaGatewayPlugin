<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\StateMachine;

use Doctrine\ORM\EntityManagerInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\AddressData;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\KlarnaRequestStructure;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\PayloadDataResolverInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\OrderManagementInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use SM\SMException;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class UpdateCheckout implements UpdateCheckoutInterface
{
    public function __construct(
        private OrderManagementInterface $orderManagement,
        private TaxRateResolverInterface $taxRateResolver,
        private OrderProcessorInterface $shippingChargesProcessor,
        private OrderNumberAssignerInterface $orderNumberAssigner,
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $parameterBag,
        private PayloadDataResolverInterface $payloadDataResolver,
    ) {
    }

    public function updateAddress(OrderInterface $order): void
    {
        if (!$this->isTransitionSupported($order)) {
            return;
        }

        $billingAddress = $order->getBillingAddress();
        assert($billingAddress instanceof AddressInterface);

        $shippingAddress = $order->getShippingAddress();
        assert($shippingAddress instanceof AddressInterface);

        $customer = $order->getCustomer();

        $shippingAddressData = (new AddressData($shippingAddress))->toArray();
        $billingAddressData = (new AddressData($billingAddress, $customer))->toArray();

        $addressData = [
            'billing_address' => $billingAddressData,
            'shipping_address' => $shippingAddressData,
        ];

        $payment = $order->getLastPayment();

        assert($payment !== null);

        $this->orderManagement->updateCheckoutAddress($payment, $addressData);
    }

    private function isTransitionSupported(OrderInterface $order): bool
    {
        return $this->isKlarnaCheckoutPaymentMethod($order) && $this->hasKlarnaOrderReference($order);
    }

    private function hasKlarnaOrderReference(OrderInterface $order): bool
    {
        $payment = $order->getLastPayment();
        assert($payment !== null);

        $details = $payment->getDetails();

        return count($details) > 0 && $details['klarna_order_id'] !== null;
    }

    private function isKlarnaCheckoutPaymentMethod(OrderInterface $order): bool
    {
        $payment = $order->getLastPayment();
        assert($payment !== null);

        $method = $payment->getMethod();
        assert($method instanceof PaymentMethodInterface);

        /** @psalm-suppress DeprecatedMethod */
        return $method->getGatewayConfig()?->getFactoryName() === 'klarna_checkout';
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws SMException
     */
    public function updateOrder(OrderInterface $order): void
    {
        if (!$this->isTransitionSupported($order)) {
            return;
        }

        $payment = $order->getLastPayment();
        if ($payment === null) {
            throw new SMException('Could not find a payment!');
        }

        try {
            $merchantData = $this->payloadDataResolver->getMerchantData($payment);
        } catch (\Exception $e) {
            throw new SMException($e->getMessage());
        }

        $optionsData = $this->payloadDataResolver->getOptionsData($payment);

        $klarnaRequestStructure = new KlarnaRequestStructure(
            order: $order,
            taxRateResolver: $this->taxRateResolver,
            shippingChargesProcessor: $this->shippingChargesProcessor,
            parameterBag: $this->parameterBag,
            orderNumberAssigner: $this->orderNumberAssigner,
            entityManager: $this->entityManager,
            merchantData: $merchantData,
            optionsData: $optionsData,
            type: KlarnaRequestStructure::UPDATE,
        );

        $payload = $klarnaRequestStructure->toArray();

        $this->orderManagement->updateOrder($order, $payload);
    }
}
