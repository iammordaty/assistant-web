<?php

namespace Assistant\Module\Common\Extension\GetId3\Adapter;

interface MetadataAdapterInterface
{
    public function setRawInfo(array $rawInfo);

    /** Zwraca metadane zawarte pliku (utworze muzycznym) */
    public function getMetadata(): array;

    /** Przygotowuje metadane do formatu używanego przez bibliotekę getID3 */
    public function prepareMetadata(array $metadata): array;
}
