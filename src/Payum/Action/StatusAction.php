<?php

declare(strict_types=1);

namespace AndersBjorkland\SyliusKlarnaGatewayPlugin\Payum\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\Generic;
use Payum\Core\Request\GetStatusInterface;
use Sylius\Component\Core\Model\PaymentInterface as SyliusPaymentInterface;

class StatusAction implements ActionInterface
{
    public function execute($request): void
    {
        RequestNotSupportedException::assertSupports($this, $request);

        assert($request instanceof Generic && $request instanceof GetStatusInterface);

        /** @var SyliusPaymentInterface $payment */
        $payment = $request->getFirstModel();

        $details = $payment->getDetails();

        match ($details['status']) {
            200 => $request->markCaptured(),
            400 => $request->markFailed(),
            default => $request->markNew()
        };
    }

    public function supports($request): bool
    {
        return $request instanceof GetStatusInterface &&
            $request instanceof Generic &&
            $request->getFirstModel() instanceof SyliusPaymentInterface;
    }
}
