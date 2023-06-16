<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Payum\Action;

use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Exception\GuzzleException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Checkout\KlarnaRequestStructure;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\AlreadyRefundedException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\Exception\ApiException;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Api\OrderManagementInterface;
use NorthCreationAgency\SyliusKlarnaGatewayPlugin\Payum\ValueObject\KlarnaApi;
use Payum\Core\Action\ActionInterface;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Core\Model\GatewayConfigInterface;
use Payum\Core\Request\Capture;
use Sylius\Bundle\OrderBundle\NumberAssigner\OrderNumberAssignerInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Order\Processor\OrderProcessorInterface;
use Sylius\Component\Taxation\Resolver\TaxRateResolverInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class RefundAction implements ActionInterface, ApiAwareInterface
{
    private ?KlarnaApi $api;

    public function __construct(
        private TaxRateResolverInterface $taxRateResolver,
        private OrderProcessorInterface $shippingChargesProcessor,
        private ParameterBagInterface $parameterBag,
        private OrderNumberAssignerInterface $orderNumberAssigner,
        private EntityManagerInterface $entityManager,
        private OrderManagementInterface $orderManagement,
    ) {
        $this->api = null;
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     */
    public function refund(SyliusPaymentInterface $payment): void
    {
        $paymentMethod = $payment->getMethod();
        assert($paymentMethod instanceof PaymentMethodInterface);
        if (!$this->supportsPaymentMethod($paymentMethod)) {
            return;
        }

        try {
            $this->sendRefundRequest($payment);
        } catch (AlreadyRefundedException $exception) {
            // Already Refunded is allowed
        }
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     */
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        assert($request instanceof Capture);

        /** @var SyliusPaymentInterface $payment */
        $payment = $request->getModel();

        $this->sendRefundRequest($payment);
    }

    /**
     * @throws ApiException
     * @throws GuzzleException
     */
    public function sendRefundRequest(SyliusPaymentInterface $payment): void
    {
        $order = $payment->getOrder();
        assert($order instanceof OrderInterface);

        $klarnaRequestStructure = new KlarnaRequestStructure(
            order: $order,
            taxRateResolver: $this->taxRateResolver,
            shippingChargesProcessor: $this->shippingChargesProcessor,
            parameterBag: $this->parameterBag,
            orderNumberAssigner: $this->orderNumberAssigner,
            entityManager: $this->entityManager,
            type: KlarnaRequestStructure::REFUND,
        );

        $payload = $klarnaRequestStructure->toArray();

        $this->orderManagement->sendRefundRequest($payment, $payload);
    }

    public function supports($request): bool
    {
        return $request instanceof Capture && $request->getModel() instanceof SyliusPaymentInterface;
    }

    public function setApi($api): void
    {
        if (!$api instanceof KlarnaApi) {
            throw new UnsupportedApiException('Expected an instance of ' . KlarnaApi::class);
        }

        $this->api = $api;
    }

    public function getApi(): ?KlarnaApi
    {
        return $this->api;
    }

    public function replacePlaceholder(string $replacement, string $string): string
    {
        $strStart = strpos($string, '{');
        $strEnd = strpos($string, '}');

        if ($strStart === false || $strEnd === false) {
            return $string;
        }

        return substr_replace($string, $replacement, $strStart, $strEnd - $strStart + 1);
    }

    public function supportsPaymentMethod(PaymentMethodInterface $paymentMethod): bool
    {
        $paymentConfig = $paymentMethod->getGatewayConfig();

        assert($paymentConfig instanceof GatewayConfigInterface);

        /** @psalm-suppress DeprecatedMethod */
        $factoryName = $paymentConfig->getFactoryName();

        return str_contains($factoryName, 'klarna_checkout');
    }
}
