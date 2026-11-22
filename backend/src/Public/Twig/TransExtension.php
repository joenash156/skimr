<?php

declare(strict_types=1);

namespace App\Public\Twig;

use App\Shared\Translator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TransExtension extends AbstractExtension
{
    public function __construct(private readonly Translator $translator)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('trans', function (array $context, string $key, array $params = []): string {
                $locale = $context['locale'] ?? null;

                return $this->translator->trans($key, $params, is_string($locale) ? $locale : null);
            }, ['needs_context' => true]),
        ];
    }
}
