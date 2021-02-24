<?php

namespace Assistant\Module\Collection\Extension\Validator;

use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Model\ModelInterface;
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
     * Obiekt walidatora katalogów
     *
     * @var DirectoryValidator
     */
    private DirectoryValidator $directoryValidator;

    /**
     * Obiekt walidatora plików (utworów muzycznych)
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
            new DirectoryRepository($container['db'])
        );

        $trackValidator = new TrackValidator(
            new TrackRepository($container['db']),
            new Id3Adapter()
        );

        return new self($directoryValidator, $trackValidator);
    }

    /**
     * @param ModelInterface|Track|Directory $node
     * @return void
     */
    public function validate(ModelInterface $node): void
    {
        $nodeClassname = get_class($node);

        if ($nodeClassname === Directory::class) {
            $this->directoryValidator->validate($node);
        }

        if ($nodeClassname === Track::class) {
            $this->trackValidator->validate($node);
        }
    }
}
