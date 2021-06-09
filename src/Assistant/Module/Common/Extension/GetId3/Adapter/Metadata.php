<?php

namespace Assistant\Module\Common\Extension\GetId3\Adapter;

/**
 * Klasa bazowa adapterów metadanych
 */
abstract class Metadata
{
    /** Surowe dane pochodzące z biblioteki getID3 */
    protected array $rawInfo;

    public function __construct(array $rawInfo)
    {
        $this->rawInfo = $rawInfo;
    }

    /** Zwraca metadane zawarte pliku (utworze muzycznym) */
    abstract public function getMetadata(): array;

    /** Przygotowuje metadane do formatu używanego przez bibliotekę getID3 */
    abstract public function prepareMetadata(array $metadata): array;
}
