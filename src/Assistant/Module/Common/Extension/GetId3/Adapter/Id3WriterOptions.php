<?php

namespace Assistant\Module\Common\Extension\GetId3\Adapter;

final readonly class Id3WriterOptions
{
    /** @noinspection SpellCheckingInspection */
    public const TAGS_TO_REMOVE = [
        'ape',
        'id3v1',
        'lyrics3',
        'real',
        'vorbiscomment',
    ];

    public function __construct(
        public string $encoding = 'UTF-8',
        /** Określa, który typ taga zostanie użyty do zapisania metadanych */
        public array $tagFormats = [ 'id3v2.3' ],
        /**
         * Określa, czy inne niż wskazane w $tagFormats typy tagów zostaną usunięte podczas zapisu metadanych
         *
         * @see self::$tagFormats
         * @see self::TAGS_TO_REMOVE
         */
        public bool $removeOtherTags = true,
    ) {
    }

    public static function defaults(): self
    {
        return new self();
    }
}
