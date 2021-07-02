<?php

namespace Assistant\Module\Collection\Extension\Reader\MetadataParser;

use Assistant\Module\Collection\Extension\Reader\MetadataParser\MetadataField\MetadataFieldInterface;

/**
 * Fasada dla parserów operujący na metadanych
 */
final class MetadataParser
{
    /** Lista nazw obsługiwanych parserów pól metadanych */
    private array $parserNames = [
        'artist',
    ];

    /**
     * Lista obiektów parserów pól metadanych
     *
     * @see setup()
     * @see $providerNames
     *
     * @var MetadataFieldInterface[]
     */
    private array $parsers = [ ];

    private array $sourceFieldToDestinationFieldMap = [
        'artist' => 'artists',
    ];

    private array $parameters;

    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;

        $this->setup();
    }

    /**
     * Parsuje metadane
     *
     * @param array $metadata
     * @return array
     */
    public function parse(array $metadata): array
    {
        $result = [];

        foreach ($this->parserNames as $parserName) {
            $destination = $this->sourceFieldToDestinationFieldMap[$parserName];

            if (isset($metadata[$parserName])) {
                $result[$destination] = $this->parsers[$parserName]->parse($metadata[$parserName]);
            }
        }

        return $result;
    }

    /** Przygotowuje parsery do użycia */
    private function setup(): void
    {
        foreach ($this->parserNames as $parserName) {
            $className = sprintf('%s\MetadataField\%s', __NAMESPACE__, ucfirst($parserName));
            $parserParameters = $this->parameters[ $parserName ] ?? null;

            $this->parsers[$parserName] = new $className($parserParameters);

            unset($parserName, $className, $parserParameters);
        }
    }
}
