<?php

declare(strict_types=1);

namespace NorthCreationAgency\SyliusKlarnaGatewayPlugin\Controller;

use Symfony\Component\HttpFoundation\Response;

class WidgetRenderController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{
    public function __construct(
        private KlarnaCheckoutController $klarnaCheckoutController,
    ) {
    }

    public function widget(string $tokenValue): Response
    {
        $snippetResponse = $this->klarnaCheckoutController->getSnippet($tokenValue);
        $content = $snippetResponse->getContent();

        if ($content === false) {
            $content = '';
        }

        /** @var array $snippet */
        $snippet = json_decode($content, true);

        return $this->render(
            '@NorthCreationAgencySyliusKlarnaGatewayPlugin/widget.html.twig',
            ['snippet' => $snippet['snippet'] ?? ''],
        );
    }
}
