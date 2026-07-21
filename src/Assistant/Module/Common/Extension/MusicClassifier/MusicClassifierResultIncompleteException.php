<?php

namespace Assistant\Module\Common\Extension\MusicClassifier;

final class MusicClassifierResultIncompleteException extends MusicClassifierException
{
    public function __construct(array $errors)
    {
        $messages = array_map(
            static fn (array $error): string => sprintf(
                '%s: %s',
                $error['analyzer'] ?? 'unknown',
                $error['message'] ?? 'unknown error',
            ),
            $errors,
        );

        $message = sprintf(
            'Music classifier returned an incomplete result%s',
            $messages ? ': ' . implode('; ', $messages) : '',
        );

        parent::__construct($message);
    }
}
