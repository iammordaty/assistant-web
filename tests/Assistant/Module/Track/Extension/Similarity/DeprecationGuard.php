<?php

namespace Assistant\Module\Track\Extension\Similarity;

use ErrorException;

/**
 * Zamienia ostrzeżenia o wycofanych konstrukcjach w wyjątki na czas testu.
 *
 * Moduł podobieństwa operuje na polach, które mogą być puste (tempo, rok, gatunek, tonacja), a PHP
 * sygnalizuje takie użycie wyłącznie ostrzeżeniem - łatwym do przeoczenia i nieodróżnialnym od
 * poprawnego wyniku. Kontrola obejmuje tylko testy korzystające z tej cechy, bo biblioteki zewnętrzne
 * zgłaszają własne ostrzeżenia, niezwiązane z tym modułem.
 */
trait DeprecationGuard
{
    /** @before */
    protected function failOnDeprecations(): void
    {
        $previousHandler = null;

        $previousHandler = set_error_handler(
            static function (int $severity, string $message, string $file, int $line) use (&$previousHandler): bool {
                if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
                    throw new ErrorException($message, 0, $severity, $file, $line);
                }

                return $previousHandler !== null
                    ? (bool) ($previousHandler)($severity, $message, $file, $line)
                    : false;
            }
        );
    }

    /** @after */
    protected function restorePreviousErrorHandler(): void
    {
        restore_error_handler();
    }
}
