<?php

namespace Assistant\Module\Collection\Extension\Validator;

use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Model\ModelInterface;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use MongoDB\Database;

/**
 * Fasada dla walidatorów plików oraz katalogów mających zostać dodanych do kolekcji
 */
class ValidatorFacade
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

    public function __construct(Database $database, array $parameters)
    {
        $this->directoryValidator = new DirectoryValidator(
            new DirectoryRepository($database),
        );

        $this->trackValidator = new TrackValidator(
            new TrackRepository($database),
            new Id3Adapter(),
            $parameters['collection']['root_dir']
        );
    }

    /**
     * @param ModelInterface|Track|Directory $item
     * @return void
     */
    public function validate(ModelInterface $item): void
    {
        $itemType = static::getItemType($item);

        if ($itemType === 'directory') {
            $this->directoryValidator->validate($item);

            return;
        }

        if ($itemType === 'track') {
            $this->trackValidator->validate($item);
        }
    }

    /**
     * Zwraca typ podanego elementu kolekcji
     *
     * @todo Chyba bardziej właściwe byłoby, gdyby to model zwracał informację o typie via getType()
     *
     * @param ModelInterface|Track|Directory $item
     * @return string
     */
    private static function getItemType(ModelInterface $item): string
    {
        $parts = explode('\\', get_class($item));

        return lcfirst(array_pop($parts));
    }
}
