<?php

namespace Assistant\Module\Search\Extension\Criteria;

final class Regex implements SearchCriteriaField
{
    public const string REGEX_CASE_INSENSITIVE = 'i';

    public function __construct(private string $pattern, private ?string $flags = null)
    {
    }

    public static function exact(string $pattern, ?array $flags = [ self::REGEX_CASE_INSENSITIVE ]): self
    {
        $regex = self::create('^' . $pattern . '$', $flags);

        return $regex;
    }

    public static function contains(string $pattern, ?array $flags = [ self::REGEX_CASE_INSENSITIVE ]): self
    {
        $regex = self::create($pattern, $flags);

        return $regex;
    }

    public static function startsWith(string $pattern, ?array $flags = [ self::REGEX_CASE_INSENSITIVE ]): self
    {
        $regex = self::create('^' . $pattern, $flags);

        return $regex;
    }

    public static function endsWith(string $pattern, ?array $flags = [ self::REGEX_CASE_INSENSITIVE ]): self
    {
        $regex = self::create($pattern . '$', $flags);

        return $regex;
    }

    public static function containsWordPart(string $pattern, ?array $flags = [ self::REGEX_CASE_INSENSITIVE ]): self
    {
        $regex = self::create('\b' . $pattern, $flags);

        return $regex;
    }

    public static function create(string $expression, array $flags = []): self
    {
        return new self($expression, implode('', $flags));
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getFlags(): ?string
    {
        return $this->flags;
    }
}
