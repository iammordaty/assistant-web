<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Common\Extension\Backend\Client as BackendClient;
use Assistant\Module\Common\Model\ModelInterface;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use Slim\Helper\Set as Container;

/**
 * Fasada dla writerów zajmujących się zapisywaniem elementów w kolekcji
 */
final class WriterFacade
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

    public function __construct(DirectoryWriter $directoryWriter, TrackWriter $trackWriter)
    {
        $this->directoryWriter = $directoryWriter;
        $this->trackWriter = $trackWriter;
    }

    public static function factory(Container $container): WriterFacade
    {
        $directoryWriter = new DirectoryWriter(
            new DirectoryRepository($container['db']),
        );

        $trackWriter = new TrackWriter(
            new TrackRepository($container['db']),
            new BackendClient(),
        );

        return new self($directoryWriter, $trackWriter);
    }

    /**
     * Zapisuje element kolekcji
     *
     * @param ModelInterface|Track|Directory $item
     * @return void
     */
    public function save(ModelInterface $item): void
    {
        $itemType = self::getItemType($item);

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
