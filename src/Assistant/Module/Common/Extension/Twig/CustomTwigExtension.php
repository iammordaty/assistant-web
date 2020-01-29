<?php

namespace Assistant\Module\Common\Extension\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class CustomTwigExtension extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'custom_twig_extension';
    }

    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return [
            new TwigFilter('formatDateAsDaysAgo', [ $this, 'formatDateAsDaysAgo' ]),
            new TwigFilter('formatSeconds', [ $this, 'formatSeconds' ]),
        ];
    }

    public function formatDateAsDaysAgo(\DateTime $inputDate): string
    {
        $now = new \DateTime();
        $interval = $now->diff($inputDate);

        switch ($interval->format('%a')) {
            case 0:
                return 'Dzisiaj';
            case 1:
                return 'Wczoraj';
            case 2:
                return 'Przedwczoraj';
        }

        return ($interval->format('%a') <= 7)
            ? $interval->format('%a') . ' dni temu'
            : $inputDate->format('d.m.Y');
    }

    public function formatSeconds(string $ss): string
    {
        $result = '';

        $s = $ss % 60;
        $m = floor(($ss % 3600) / 60);
        $h = floor(($ss % 86400) / 3600);
        $d = floor(($ss % 2592000) / 86400);

        if ($d > 0) {
            $result .= $d . ':';
        }

        if ($h > 0) {
            $result .= ($h < 10 ? '0' : '') . $h . ':';
        }

        if ($m > 0 || $s > 0) {
            $result .= ($m < 10 ? '0' : '') . $m . ':';
        }

        $result .= ($s < 10 ? '0' : '') . $s;

        return $result;
    }
}
