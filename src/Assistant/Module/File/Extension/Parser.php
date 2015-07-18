<?php

namespace Assistant\Module\File\Extension;

class Parser
{
    /**
     * @var array
     */
    protected $parsers = [
        'artist' => 'artists',
    ];

    /**
     * @var array
     */
    protected $parameters;

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

    public function parse(array $metadata)
    {
        $result = [];

        foreach ($this->parsers as $parser => $field) {
            $result[$field] = $this->{ lcfirst($parser) }->parse($metadata[$parser]);
        }

        return $result;
    }

    protected function setup()
    {
        foreach (array_keys($this->parsers) as $parser) {
            $className = sprintf('%s\Parser\Field\%s', __NAMESPACE__, ucfirst($parser));
            $parserParameters = isset($this->parameters[$parser]) ? $this->parameters[$parser] : null;

            $this->{ lcfirst($parser) } = new $className($parserParameters);
        }
    }
}
