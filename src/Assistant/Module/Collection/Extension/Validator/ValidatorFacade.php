<?php

namespace Assistant\Module\Collection\Extension\Validator;

use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Model\CollectionItemInterface;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use Slim\Helper\Set as Container;

/**
 * Fasada dla walidatorów plików oraz katalogów mających zostać dodanych do kolekcji
 */
final class ValidatorFacade
{
    /**
     * Obiekt klasy walidującej katalogi
     *
     * @var DirectoryValidator
     */
    private DirectoryValidator $directoryValidator;

    /**
     * Obiekt klasy walidujący pliki (utwory muzyczne)
     *
     * @var TrackValidator
     */
    private TrackValidator $trackValidator;

    public function __construct(DirectoryValidator $directoryValidator, TrackValidator $trackValidator)
    {
        $this->directoryValidator = $directoryValidator;
        $this->trackValidator = $trackValidator;
    }

    public static function factory(Container $container): ValidatorFacade
    {
        $directoryValidator = new DirectoryValidator(
            $container[DirectoryRepository::class]
        );

        $trackValidator = new TrackValidator(
            $container[TrackRepository::class],
            new Id3Adapter()
        );

        return new self($directoryValidator, $trackValidator);
    }

    /**
     * @param CollectionItemInterface|Track|Directory $node
     * @return void
     */
    public function validate(CollectionItemInterface $node): void
    {
        if ($node instanceof Directory) {
            $this->directoryValidator->validate($node);
        }

        if ($node instanceof Track) {
            $this->trackValidator->validate($node);
        }
    }
}
