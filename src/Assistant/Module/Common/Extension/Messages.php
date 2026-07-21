<?php

namespace Assistant\Module\Common\Extension;

use Slim\Flash\Messages as FlashMessages;

final class Messages
{
    public const string SUCCESS = 'success';
    public const string ERROR = 'error';
    public const string WARNING = 'warning';
    public const string INFO = 'info';

    private FlashMessages $messages;

    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !isset($_SESSION)) {
            session_start();
        }

        $this->messages = new FlashMessages();
    }

    public function addSuccess(string $message): void
    {
        $this->messages->addMessage(self::SUCCESS, $message);
    }

    public function addError(string $message): void
    {
        $this->messages->addMessage(self::ERROR, $message);
    }

    public function addWarning(string $message): void
    {
        $this->messages->addMessage(self::WARNING, $message);
    }

    public function addInfo(string $message): void
    {
        $this->messages->addMessage(self::INFO, $message);
    }

    /**
     * Zwraca komunikaty przeznaczone do wyświetlenia w bieżącym żądaniu, pogrupowane po typie.
     *
     * @return array<string, string[]>
     */
    public function all(): array
    {
        return $this->messages->getMessages();
    }
}
