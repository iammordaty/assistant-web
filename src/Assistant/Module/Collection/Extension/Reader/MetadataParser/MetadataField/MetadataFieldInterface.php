<?php

namespace Assistant\Module\Collection\Extension\Reader\MetadataParser\MetadataField;

interface MetadataFieldInterface
{
    /** Parsuje wartość tagu */
    public function parse(string $value): mixed;
}
