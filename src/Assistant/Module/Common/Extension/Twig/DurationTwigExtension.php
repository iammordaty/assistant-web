<?php

namespace Assistant\Module\Common\Extension\Twig;

use Khill\Duration\Duration;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class DurationTwigExtension extends AbstractExtension
{
    private Duration $duration;

    public function __construct(Duration $duration)
    {
        $this->duration = $duration;
    }

    public static function factory(?Duration $duration = null): DurationTwigExtension
    {
        $duration = $duration ?: new Duration();

        return new self($duration);
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters(): array
    {
        return [
            /** @uses getFormattedDuration */
            new TwigFilter('format_duration', [ $this, 'getFormattedDuration' ]),
        ];
    }

    public function getFormattedDuration(int $seconds): string
    {
        $formatted = $this->duration->formatted($seconds, true);

        if ($this->duration->hours === 0) {
            $formatted = str_replace('0:', '', $formatted);
        }

        return $formatted;
    }
}
