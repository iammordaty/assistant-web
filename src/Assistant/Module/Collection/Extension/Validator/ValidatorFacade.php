<?php

namespace Assistant\Module\Collection\Extension\Validator;

use Assistant\Module\Collection\Model\CollectionItemInterface;
use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Directory\Extension\DirectoryService;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Model\Track;
use Psr\Container\ContainerInterface as Container;

/** Fasada dla walidatorów plików oraz katalogów mających zostać dodanych do kolekcji */
final class ValidatorFacade
{
    /** Obiekt klasy walidującej katalogi */
    private DirectoryValidator $directoryValidator;

    /** Obiekt klasy walidujący pliki (utwory muzyczne) */
    private TrackValidator $trackValidator;

    public function __construct(DirectoryValidator $directoryValidator, TrackValidator $trackValidator)
    {
        $this->directoryValidator = $directoryValidator;
        $this->trackValidator = $trackValidator;
    }

    public static function factory(Container $container): self
    {
        $directoryValidator = new DirectoryValidator(
            $container->get(DirectoryService::class)
        );

        $trackValidator = new TrackValidator(
            $container->get(TrackService::class),
            $container->get(Id3Adapter::class),
        );

        return new self($directoryValidator, $trackValidator);
    }

    public function validate(CollectionItemInterface|Directory|Track $node)
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
