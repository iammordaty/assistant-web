<?php

namespace Assistant\Module\Search\Extension\Request;

use Assistant\Module\Search\Extension\Criteria\MinMaxInfo;
use Assistant\Module\Search\Extension\Criteria\Regex;
use Assistant\Module\Search\Extension\Criteria\SearchCriteria;
use Assistant\Module\Search\Extension\Request\ExpressionParser\DateTimeMinMaxExpressionParser;
use Assistant\Module\Search\Extension\Request\ExpressionParser\ExpressionParser;
use Assistant\Module\Search\Extension\Request\ExpressionParser\NameParser;
use Assistant\Module\Search\Extension\Request\ExpressionParser\NumberMinMaxExpressionParser;
use Assistant\Module\Search\Extension\Request\ExpressionParser\YearMinMaxExpressionParser;
use Slim\Http\ServerRequest;

// przed usunięciem 'artist' i 'title' trzeba pozmieniać wywołania routingu
// - src/Assistant/Module/Track/Resources/templates/common/header.twig
// - src/Assistant/Module/Dashboard/Resources/templates/index.twig
final readonly class SearchRequest
{
    // pomyśleć o wyciągnięciu tego do klasy Form i zamiana array $form na SearchForm $form, ale bez spiny
    public const array DEFAULTS = [
        'artist' => '',
        'bpm' => '',
        'genre' => '',
        'guid' => '',
        'indexed_date' => '',
        'initial_key' => '',
        'is_favorite' => null,
        'name' => '',
        'publisher' => '',
        'title' => '',
        'year' => '',
    ];

    private function __construct(
        private ?string $name = null,
        private ?string $guid = null,
        private ?string $artist = null,
        private ?string $title = null,
        private ?array $genres = null,
        private ?array $publishers = null,
        private MinMaxInfo|array|null $years = null,
        private ?array $initialKeys = null,
        private MinMaxInfo|array|null $bpm = null,
        private ?bool $isFavorite = null,
        private MinMaxInfo|array|null $indexedDates = null,
        private ?array $pathname = null,
        private array $form = [],
        private bool $isFormSubmitted,
        private bool $hasNameModifiers,
    ) {
    }

    public static function fromServerRequest(ServerRequest $request): self
    {
        [ $form, $hasNameModifiers ] = self::normalizeForm($request->getQueryParams());

        return new self(
            name: $form['name'] ?: null,
            guid: $form['guid'] ?: null,
            artist: $form['artist'] ?: null,
            title: $form['title'] ?: null,
            genres: self::parseCommaSeparated($form['genre']),
            publishers: self::parseCommaSeparated($form['publisher']),
            years: self::parseMinMaxOrList($form['year'], YearMinMaxExpressionParser::class, 'intval'),
            initialKeys: self::parseInitialKeys($form['initial_key']),
            bpm: self::parseMinMaxOrList($form['bpm'], NumberMinMaxExpressionParser::class, 'floatval'),
            isFavorite: filter_var($form['is_favorite'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            indexedDates: self::parseMinMaxOrList(
                $form['indexed_date'],
                DateTimeMinMaxExpressionParser::class,
                'intval'
            ),
            pathname: $form['pathname'] ?? null,
            form: $form,
            isFormSubmitted: self::isSubmitted($form),
            hasNameModifiers: $hasNameModifiers,
        );
    }

    public function toSearchCriteria(): SearchCriteria
    {
        $name = $this->name;
        $artist = null;
        $title = null;

        if ($this->name) {
            $parsedName = NameParser::parse($this->name);
            $modifiers = $parsedName?->getModifiers() ?? [];
            $name = $parsedName?->getFreeText();

            if ($modifiers !== []) {
                if (isset($modifiers['artist'])) {
                    $artist = Regex::contains($modifiers['artist']);
                }

                $titleTerm = $modifiers['title'] ?? null;
                $remixTerm = $modifiers['remix'] ?? null;

                if ($titleTerm && $remixTerm) {
                    $title = NameParser::titleAndRemixRegex($titleTerm, $remixTerm);
                } elseif ($titleTerm) {
                    $title = NameParser::titleOnlyRegex($titleTerm);
                } elseif ($remixTerm) {
                    $title = NameParser::remixRegex($remixTerm);
                }
            }
        } else {
            $artist = $this->artist ? Regex::contains($this->artist) : null;
            $title = $this->title ? Regex::contains($this->title) : null;
        }

        return new SearchCriteria(
            name: $name,
            guid: $this->guid ? Regex::exact($this->guid) : null,
            artist: $artist,
            title: $title,
            genres: $this->genres ? array_map(fn ($g) => Regex::exact($g), $this->genres) : null,
            publishers: $this->publishers ? array_map(fn ($p) => Regex::containsWordPart($p), $this->publishers) : null,
            years: $this->years,
            initialKeys: $this->initialKeys,
            bpm: $this->bpm,
            isFavorite: $this->isFavorite ?: null,
            indexedDates: $this->indexedDates,
            pathname: $this->pathname,
        );
    }

    private static function parseCommaSeparated(?string $value): ?array
    {
        if (!$value) {
            return null;
        }

        $items = explode(',', $value);
        $items = array_map('trim', $items);
        $items = array_filter($items, fn ($item) => $item !== '');
        $items = array_unique($items);

        return $items !== [] ? array_values($items) : null;
    }

    private static function parseInitialKeys(?string $value): ?array
    {
        $keys = self::parseCommaSeparated($value);

        if (!$keys) {
            return null;
        }

        return array_map('strtoupper', $keys);
    }

    private static function parseMinMaxOrList(?string $value, string $parser, string $castFn): MinMaxInfo|array|null
    {
        if (!$value) {
            return null;
        }

        /** @var ExpressionParser $parser */
        $parsed = $parser::parse($value);

        if ($parsed instanceof MinMaxInfo) {
            return $parsed;
        }

        $items = self::parseCommaSeparated($value);

        if (!$items) {
            return null;
        }

        return array_map($castFn, $items);
    }

    public function getForm(): array
    {
        return $this->form;
    }

    public function isFormSubmitted(): bool
    {
        return $this->isFormSubmitted;
    }

    public function hasNameModifiers(): bool
    {
        return $this->hasNameModifiers;
    }

    public function withForm(array $form): self
    {
        $request = clone($this, [
            'form' => [ ...$this->form, ...$form ],
        ]);

        return $request;
    }

    private static function normalizeForm(array $params): array
    {
        $normalizedForm = [ ...self::DEFAULTS, ...$params ];

        $hasNameModifiers = false;

        if ($normalizedForm['name']) {
            $hasNameModifiers = (bool) preg_match('/(a|artist|t|title|r|remix):/i', $normalizedForm['name']);
        }

        $artist = trim((string) ($normalizedForm['artist'] ?? ''));

        if ($artist) {
            $normalizedForm['name'] .= ' artist: ' . $artist;

            // unset($normalizedForm['artist']);
        }

        $title = trim((string) ($normalizedForm['title'] ?? ''));

        if ($title) {
            $normalizedForm['name'] .= ' title: ' . $title;

            // unset($normalizedForm['title']);
        }

        $normalizedForm['name'] = trim($normalizedForm['name']);

        return [ $normalizedForm, $hasNameModifiers ];
    }

    private static function isSubmitted(array $params): bool
    {
        if (!empty($params['name'])) {
            return true;
        }

        $hasAtLeastOneValue = count(array_filter(array_values($params))) >= 1;

        return $hasAtLeastOneValue;
    }
}
