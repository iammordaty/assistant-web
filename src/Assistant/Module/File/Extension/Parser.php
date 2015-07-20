<?php

namespace Assistant\Module\File\Extension;

/**
 * Fasada dla parserów operująch na metadanych
 */
class Parser
{
    /**
     * Lista parserów pól metadanych
     *
     * @var array
     */
    private $parserNames = [
        'artist',
    ];

    /**
     * Lista parserów pól metadanych
     *
     * @see setup()
     * @see $providerNames
     *
     * @var Parser\Field
     */
    private $parsers = [ ];

    /**
     * @var array
     */
    private $sourceFieldToDestinationFieldMap = [
        'artist' => 'artists',
    ];

    /**
     * @var array
     */
    private $parameters;

    /**
     * Konstruktor
     *
     * @param array $parameters
     */
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
    public function parse(array $metadata)
    {
        $result = [];

        foreach ($this->parserNames as $parserName) {
            $destination  = $this->sourceFieldToDestinationFieldMap[$parserName];
            $result[$destination] = $this->parsers[$parserName]->parse($metadata[$parserName]);
        }

        return $result;
    }

    /**
     * Przygotowuje parsery do użycia
     */
    protected function setup()
    {
        foreach ($this->parserNames as $parserName) {
            $className = sprintf('%s\Parser\Field\%s', __NAMESPACE__, ucfirst($parserName));
            $parserParameters = isset($this->parameters[$parserName]) ? $this->parameters[$parserName] : null;

            $this->parsers[$parserName] = new $className($parserParameters);

            unset($parserName, $className, $parserParameters);
        }
    }
}
