<?php

namespace Assistant\Module\File\Extension\Parser\Field;

use Assistant\Module\File\Extension\Parser\Field as BaseField;

final class Artist extends BaseField
{
    /**
     * Lista wyjątków, które nie są rozdzielane
     *
     * @var array
     */
    private $exceptions = [];

    public function parse(string $value): array
    {
        if (in_array($value, $this->parameters['exceptions'])) {
            return [ $value ];
        }

        $artists = $this->explode(
            $this->parameters['delimiters'],
            str_replace($this->parameters['exceptions'], $this->exceptions['placeholders'], $value)
        );

        return str_replace($this->exceptions['placeholders'], $this->parameters['exceptions'], $artists);
    }

    protected function setup(): void
    {
        $this->exceptions = [
            'values' => $this->parameters['exceptions'],
            'placeholders' => [ ],
        ];

        foreach ($this->parameters['exceptions'] as $exception) {
            $this->exceptions['placeholders'][] = str_replace(' ', '-', $exception);
        }
    }

    private function explode($delimiters, $artist): array
    {
        $artists = explode(
            $delimiters[0],
            str_replace($delimiters, $delimiters[0], $artist)
        );

        return array_map('trim', $artists);
    }
}
