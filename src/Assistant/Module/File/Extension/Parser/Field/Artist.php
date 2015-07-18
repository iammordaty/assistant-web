<?php

namespace Assistant\Module\File\Extension\Parser\Field;

use Assistant\Module\File\Extension\Parser\Field as BaseField;

class Artist extends BaseField
{
    /**
     * @var array
     */
    private $exceptions = [ ];

    public function parse($artist)
    {
        if (in_array($artist, $this->parameters['exceptions'])) {
            return [ $artist ];
        }

        $artists = $this->explode(
            $this->parameters['delimiters'],
            str_replace($this->parameters['exceptions'], $this->exception['placeholders'], $artist)
        );

        return str_replace($this->exception['placeholders'], $this->parameters['exceptions'], $artists);
    }

    protected function setup()
    {
        $this->exceptions = [
            'values' => $this->parameters['exceptions'],
            'placeholders' => [ ],
        ];

        foreach ($this->parameters['exceptions'] as $exception) {
            $this->exception['placeholders'][] = str_replace(' ', '-', $exception);
        }
    }

    private function explode($delimiters, $artist)
    {
        $artists = explode(
            $delimiters[0],
            str_replace($delimiters, $delimiters[0], $artist)
        );

        return array_map('trim', $artists);
    }
}
