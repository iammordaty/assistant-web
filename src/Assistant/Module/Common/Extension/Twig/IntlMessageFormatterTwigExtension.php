<?php

namespace Assistant\Module\Common\Extension\Twig;

use IntlDateFormatter;
use IntlException;
use MessageFormatter;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class IntlMessageFormatterTwigExtension extends AbstractExtension
{
    public function __construct(private string $locale = 'pl_PL.utf8')
    {
    }

    /** {@inheritdoc} */
    public function getFilters(): array
    {
        return [
            new TwigFilter('pluralize', [ $this, 'pluralize' ]),
            new TwigFilter('datetime', [ $this, 'datetime' ]),
        ];
    }

    /**
     * @see https://messageformat.github.io/messageformat/guide/#pluralformat
     * @see https://unicode-org.github.io/cldr-staging/charts/latest/supplemental/language_plural_rules.html
     */
    public function pluralize(int $number, array $rules): string
    {
        $pattern = array_reduce(
            array_keys($rules),
            fn (string $prevValue, string $ruleName) => (
                sprintf('%s%s{# %s} ', $prevValue, $ruleName, $rules[$ruleName])
            ),
            ''
        );

        $pattern = sprintf('{COUNT, plural, %s}', $pattern);

        try {
            $message = $this
                ->createMessageFormatter($pattern)
                ->format([ 'COUNT' => $number ]);
        } catch (IntlException) {
            $message = sprintf('%d %s', $number, $rules[array_key_first($rules)]);
        }

        return $message;
    }

    public function datetime(mixed $datetime, int $dateType, string $pattern): false|string
    {
        $formattedDate = $this
            ->createDateFormatter($dateType, $pattern)
            ->format($datetime);

        return $formattedDate;
    }

    private function createDateFormatter(int $dateType, string $pattern, ?string $locale = null): IntlDateFormatter
    {
        $locale = $locale ?: $this->locale;

        return new IntlDateFormatter(locale: $locale, dateType: $dateType, pattern: $pattern);
    }

    /** @throws IntlException */
    private function createMessageFormatter(string $pattern, ?string $locale = null): MessageFormatter
    {
        $locale = $locale ?: $this->locale;

        return new MessageFormatter($locale, $pattern);
    }
}
