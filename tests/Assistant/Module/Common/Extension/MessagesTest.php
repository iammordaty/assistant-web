<?php

namespace Assistant\Module\Common\Extension;

use PHPUnit\Framework\TestCase;

final class MessagesTest extends TestCase
{
    protected function setUp(): void
    {
        // ustawiona tablica sesji sprawia, że wrapper nie uruchamia realnej sesji (session_start)
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        unset($_SESSION);
    }

    /** Komunikaty dodane w jednym żądaniu są dostępne w kolejnym (wzorzec PRG, przez sesję) */
    public function testMessagesRoundTripThroughSession(): void
    {
        $writer = new Messages();
        $writer->addSuccess('Zapisano');
        $writer->addError('Błąd');
        $writer->addWarning('Uwaga');
        $writer->addInfo('Info');

        $reader = new Messages();
        $all = $reader->all();

        self::assertSame([ 'Zapisano' ], $all[Messages::SUCCESS]);
        self::assertSame([ 'Błąd' ], $all[Messages::ERROR]);
        self::assertSame([ 'Uwaga' ], $all[Messages::WARNING]);
        self::assertSame([ 'Info' ], $all[Messages::INFO]);
    }

    /** Ten sam typ może nieść wiele komunikatów */
    public function testMultipleMessagesOfSameType(): void
    {
        $writer = new Messages();
        $writer->addWarning('Pierwsze');
        $writer->addWarning('Drugie');

        self::assertSame([ 'Pierwsze', 'Drugie' ], (new Messages())->all()[Messages::WARNING]);
    }

    public function testNoMessagesReturnsEmptyArray(): void
    {
        self::assertSame([], (new Messages())->all());
    }
}
