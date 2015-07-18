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
            'formatDateAsTimeAgo' => new \Twig_Filter_Method($this, 'formatDateAsTimeAgo'),
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
     * formatDateAsTimeAgo
     *
     * @param \DateTime $inputDate
     */
    public function formatDateAsTimeAgo($inputDate)
    {
        $inputTs = $inputDate->format('U');
        $now = (new \DateTime())->format('U');

        if ($inputTs > $now) {
            return 'Dzisiaj';
        }

        $dateSuffix = ', ' . $inputDate->format('Y-m-d');
        $timeSuffix = ' o ' . $inputDate->format('H:i');
        $diff = $now - $inputTs;

        $minutes = floor($diff / 60);
        $hours = floor($minutes / 60);
        $days = floor($hours / 24);
        $months = intval((date('Y', $now) - date('Y', $inputTs)) * 12 + (date('m', $now) - date('m', $inputTs)));

        if ($minutes <= 60) {
            switch ($minutes) {
                case 0:
                    return 'przed chwilą';
                case 1:
                    return 'minutę temu';
                case ($minutes >= 2 && $minutes <= 4):
                case ($minutes >= 22 && $minutes <= 24):
                case ($minutes >= 32 && $minutes <= 34):
                case ($minutes >= 42 && $minutes <= 44):
                case ($minutes >= 52 && $minutes <= 54):
                    return $minutes . ' minuty temu';
                default:
                    return $minutes . ' minut temu';
            }
        }

        $dayAgo = $now - (60 * 60 * 24);
        $twoDaysAgo = $now - (60 * 60 * 24 * 2);

        if ($hours > 0 && $hours <= 6) {
            if ($hours === 1) {
                return 'Godzinę temu';
            } else {
                if ($hours > 1 && $hours < 5) {
                    return $hours . ' godziny temu';
                }
                if ($hours > 4) {
                    return $hours . ' godzin temu';
                }
            }
        } elseif (date('Y-m-d', $inputTs) == date('Y-m-d', $now)) {
            return 'Dzisiaj' . $timeSuffix;
        } elseif (date('Y-m-d', $dayAgo) == date('Y-m-d', $inputTs)) {
            return 'Wczoraj' . $timeSuffix;
        } elseif (date('Y-m-d', $twoDaysAgo) == date('Y-m-d', $inputTs)) {
            return 'Przedwczoraj' . $timeSuffix;
        }

        if ($days > 0 && $days <= intval(date('t', $inputTs))) {
            switch ($days) {
                case ($days < 7):
                    return $days . ' dni temu' . $dateSuffix;
                case 7:
                    return 'Tydzień temu' . $dateSuffix;
                case ($days > 7 && $days < 14):
                    return 'Ponad tydzień temu' . $dateSuffix;
                case 14:
                    return 'Dwa tygodnie temu' . $dateSuffix;
                case ($days > 14 && $days < 21):
                    return 'Ponad 2 tygodnie temu' . $dateSuffix;
                case 21:
                    return '3 tygodnie temu, ' . date('Y-m-d', $inputTs);
                case ($days > 21 && $days < 28):
                    return 'Ponad 3 tygodnie temu' . $dateSuffix;
                case ($days >= 28 && $days < 32):
                    return 'Miesiąc temu';
            }
        }

        if ($months > 0 && $months <= 12) {
            switch ($months) {
                case 1:
                    return 'Miesiąc temu' . $dateSuffix;
                case 2:
                case 3:
                case 4:
                    return $months . ' miesiące temu' . $dateSuffix;
                default:
                    return $months . ' miesiecy temu' . $dateSuffix;
            }
        }

        return $inputDate->format('Y-m-d');
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
