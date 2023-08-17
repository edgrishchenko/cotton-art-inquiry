<?php

namespace Pix\Inquiry\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CsrfFunctionExtension extends AbstractExtension
{

    public function getFunctions(): array
    {
        if (class_exists(\Shopware\Storefront\Framework\Twig\Extension\CsrfFunctionExtension::class)) {
            return [
                new TwigFunction('sw_csrf', [new \Shopware\Storefront\Framework\Twig\Extension\CsrfFunctionExtension, 'createCsrfPlaceholder'], ['is_safe' => ['html']]),
            ];
        }

        return [
            new TwigFunction('sw_csrf', [$this, 'createCsrfPlaceholder'], ['is_safe' => ['html']]),
        ];
    }

    public function createCsrfPlaceholder(string $intent, array $parameters = []): string
    {
        return '';
    }
}
