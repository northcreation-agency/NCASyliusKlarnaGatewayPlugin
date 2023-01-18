<?php

declare(strict_types=1);

namespace AndersBjorkland\SyliusKlarnaGatewayPlugin\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

class KlarnaCheckoutController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{

    public function getSnippet(string $code): \Symfony\Component\HttpFoundation\Response
    {
        return new JsonResponse(
            [
                'snippet' => '<h1>Hello World</h1>',
            ]
        );
    }
}
