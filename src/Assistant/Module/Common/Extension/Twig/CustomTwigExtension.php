<?php

namespace Assistant\Module\Common\Extension\Twig;

use Khill\Duration\Duration;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class CustomTwigExtension extends AbstractExtension
{
    private Duration $duration;

    public function __construct(Duration $duration)
    {
        $this->duration = $duration;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return [
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

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'custom_twig_extension';
    }
}
