<?php

namespace Assistant\Module\Collection\Extension\Validator;

use Assistant\Module\Collection\Model\CollectionItemInterface;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Track\Model\Track;
use Psr\Container\ContainerInterface;

/** Fasada dla walidatorów plików oraz katalogów mających zostać dodanych do kolekcji */
final class ValidatorFacade
{
    private function __construct(
        private DirectoryValidator $directoryValidator,
        private TrackValidator $trackValidator
    ) {
    }

    public static function factory(ContainerInterface $container): self
    {
        $directoryValidator = DirectoryValidator::factory($container);
        $trackValidator = TrackValidator::factory($container);

        return new self($directoryValidator, $trackValidator);
    }

    public function validate(CollectionItemInterface|Directory|Track $node): void
    {
        if ($node instanceof Directory) {
            $this->directoryValidator->validate($node);

            return;
        }

        // to jest ok, ale FileReader czyta także katalog incoming (zwracając obiekt typu IncomingTrack)
        // więc może bardziej eleganckie byłoby rzucanie w takiej sytuacji wyjątku w klasie TrackValidator.
        assert($node instanceof Track);

        $this->trackValidator->validate($node);
    }
}
