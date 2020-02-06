<?php

namespace Assistant\Module\Common\Extension\Twig;

use Khill\Duration\Duration;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Westsworld\TimeAgo;

class CustomTwigExtension extends AbstractExtension
{
    private TimeAgo $timeAgo;

    private Duration $duration;

    public function __construct(TimeAgo $timeAgo, Duration $duration)
    {
        $this->timeAgo = $timeAgo;
        $this->duration = $duration;
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return [
            new TwigFilter('time_ago', [ $this, 'getTimeAgoInWords' ]),
            new TwigFilter('format_duration', [ $this, 'getFormattedDuration' ]),
        ];
    }

    public function getTimeAgoInWords(\DateTime $date): string
    {
        return $this->timeAgo->inWords($date);
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
