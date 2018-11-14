<?php

namespace Assistant\Module\Common\Extension\Twig;

class CustomTwigExtension extends \Twig_Extension
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
            'truncateText' => new \Twig_Filter_Method($this, 'truncateText'),
            'formatDateAsDaysAgo' => new \Twig_Filter_Method($this, 'formatDateAsDaysAgo'),
            'formatSeconds' => new \Twig_Filter_Method($this, 'formatSeconds'),
            'timeAgoFormat' => new \Twig_Filter_Method($this, 'timeAgoFormat'),
            'pluralize' => new \Twig_Filter_Method($this, 'pluralize'),
        ];
    }

    /**
     * Funkcja przycina teskt o wybraną ilość znaków, jak przycięta ilość znaków wypadnie w środku wyrazu.
     * Wyszukuje najbliższy znak $break.
     *
     * @param text $string - tekst
     * @param int $limit - limit znaków, który musi być tekst przycięty
     * @param string $break - znak, który jest wyszukiwany
     * @param string $pad - znacznik, który jest pokazywany przy przyciętym tekście
     */
    public function truncateText($string, $limit, $break = ' ', $pad = '...')
    {
        if (strlen($string) <= $limit) {
            return $string;
        }

        if (false !== ($breakpoint = strpos($string, $break, $limit))) {
            if ($breakpoint < strlen($string) - 1) {
                $string = substr($string, 0, $breakpoint) . $pad;
            }
        }

        return $string;
    }

    /**
     * formatDateAsDaysAgo
     *
     * @param \DateTime $inputDate
     */
    public function formatDateAsDaysAgo($inputDate)
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

    public function formatSeconds($ss)
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

    public function pluralize($value, $string1, $string2, $string5)
    {
        if ($value == 1) {
            return $value . ' ' . $string1;
        }

        $jedn = ($value % 10);
        $dzies = (intval($value / 10) % 10);

        if ($jedn > 1 && $jedn < 5 && $dzies <> 1) {
            return $value . ' ' . $string2;
        }

        return $value . " " . $string5;
    }
}
