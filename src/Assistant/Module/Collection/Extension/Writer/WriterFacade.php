<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Common\Extension\Backend\Client as BackendClient;
use Assistant\Module\Common\Model\ModelInterface;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use MongoDB\Database;

/**
 * Fasada dla writerów zajmujących się zapisywaniem elementów w kolekcji
 */
class WriterFacade
{
    /**
     * Obiekt klasy odpowiedzialnej za zapis katalogów w bazie danych
     *
     * @var DirectoryWriter
     */
    private DirectoryWriter $directoryWriter;

    /**
     * Obiekt klasy odpowiedzialnej za zapis plików (utworów muzycznych) w bazie danych
     *
     * @var TrackWriter
     */
    private TrackWriter $trackWriter;

    /**
     * Konstruktor
     *
     * @param Database $database
     */
    public function __construct(Database $database)
    {
        $this->directoryWriter = new DirectoryWriter(
            new DirectoryRepository($database),
        );

        $this->trackWriter = new TrackWriter(
            new TrackRepository($database),
            new BackendClient(),
        );
    }

    /**
     * Zapisuje element kolekcji
     *
     * @param ModelInterface|Track|Directory $item
     * @return void
     */
    public function save(ModelInterface $item): void
    {
        $itemType = static::getItemType($item);

        if ($itemType === 'directory') {
            $this->directoryWriter->save($item);
        }

        if ($itemType === 'track') {
            $this->trackWriter->save($item);
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
