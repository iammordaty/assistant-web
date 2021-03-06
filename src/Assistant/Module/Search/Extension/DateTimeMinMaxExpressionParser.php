<?php

namespace Assistant\Module\Search\Extension;

use DateTimeInterface;

final class DateTimeMinMaxExpressionParser extends RawMinMaxExpressionParser
{
    private const FORMATS = [
        'Y',
        'm.Y',
        'd.m.Y',
        'Y.m',
        'Y.m.d',
    ];

    private const MIN_MODIFIERS = [
        'first day of january midnight',
        'first day of this month midnight',
        'midnight',
        'first day of this month midnight',
        'first day of january midnight',
    ];

    public const MAX_MODIFIERS = [
        'last day of december midnight - 1 second',
        'last day of this month midnight - 1 second',
        'midnight - 1 second',
        'last day of this month midnight - 1 second',
        'last day of december midnight - 1 second',
    ];

    public static function parse(string $expression): ?MinMaxInfo
    {
        $minMaxInfo = parent::parse($expression);

        if ($minMaxInfo === null) {
            return null;
        }

        [ $min, $max ] = array_values($minMaxInfo->get());

        $dates = [
            $min ? self::toMinDateTime($min) : null,
            $max ? self::toMaxDateTime($max) : null,
        ];

        $dateTimeMinMaxInfo = array_combine(array_keys($minMaxInfo->get()), $dates);

        return MinMaxInfo::create($dateTimeMinMaxInfo);
    }

    private static function toMinDateTime(?string $value): ?DateTimeInterface
    {
        $formatToMinModifierMap = array_combine(self::FORMATS, self::MIN_MODIFIERS);

        return self::toDateTime($value, $formatToMinModifierMap);
    }

    private static function toMaxDateTime(?string $value): ?DateTimeInterface
    {
        $formatToMaxModifierMap = array_combine(self::FORMATS, self::MAX_MODIFIERS);

        return self::toDateTime($value, $formatToMaxModifierMap);
    }

    private static function toDateTime(?string $value, array $formatToModifierMap): \DateTime|null
    {
        $normalizedValue = self::normalizeValue($value);

        if (!$normalizedValue) {
            return null;
        }

        $dateTime = null;

        foreach ($formatToModifierMap as $format => $modifier) {
            $dateTime = \DateTime::createFromFormat($format, $normalizedValue);

            if ($dateTime) {
                $dateTime = $dateTime->modify($modifier);

                break;
            }
        }

        return $dateTime;
    }

    private static function normalizeValue(string $value): string
    {
        $normalizedValue = str_replace('-', '.', trim($value, '.'));

        return $normalizedValue;
    }
}
