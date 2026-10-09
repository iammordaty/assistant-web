<?php

namespace Assistant\Module\Track\Extension\Similarity;

/**
 * AI: preset z celem wyliczonym na serwerze dla bieżącego utworu; widok przekazuje go do przycisku presetu,
 * dzięki czemu JS tylko wpisuje gotowe wartości do formularza i nie powiela arytmetyki koła Camelota
 */
final readonly class RelatedTracksPreset
{
    public function __construct(
        public RelatedTracksMode $mode,
        /** @var string[] */
        public array $providers,
        /** @var array<string, string|int|float|null> wartości pól formularza według nazwy dostawcy */
        public array $values,
    ) {
    }
}
