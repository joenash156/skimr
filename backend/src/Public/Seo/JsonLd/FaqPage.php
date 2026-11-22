<?php

declare(strict_types=1);

namespace App\Public\Seo\JsonLd;

final class FaqPage
{
    /**
     * @param list<array{question: string, answer: string}> $items
     * @return array<string, mixed>
     */
    public static function build(string $url, string $locale, array $items): array
    {
        return [
            '@type' => 'FAQPage',
            '@id' => $url . '#faq',
            'inLanguage' => $locale,
            'mainEntity' => array_map(
                static fn (array $item): array => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $item['answer'],
                    ],
                ],
                $items,
            ),
        ];
    }
}
