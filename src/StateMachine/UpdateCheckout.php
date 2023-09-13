<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\StateMachine;

use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\AddressData;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\OrderManagementInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;

class UpdateCheckout implements UpdateCheckoutInterface
{
    public function __construct(
        private OrderManagementInterface $orderManagement,
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
}
