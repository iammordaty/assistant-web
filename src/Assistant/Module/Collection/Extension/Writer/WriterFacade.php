<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Common\Extension\Backend\Client as BackendClient;
use Assistant\Module\Collection\Model\CollectionItemInterface;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Directory\Repository\DirectoryRepository;
use Assistant\Module\Search\Extension\TrackSearchService;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Model\Track;
use Psr\Container\ContainerInterface as Container;

/** Fasada dla writerów zajmujących się zapisywaniem elementów w kolekcji */
final class WriterFacade
{
    /** Obiekt klasy odpowiedzialnej za zapis katalogów w bazie danych */
    private DirectoryWriter $directoryWriter;

    /** Obiekt klasy odpowiedzialnej za zapis plików (utworów muzycznych) w bazie danych */
    private TrackWriter $trackWriter;

    public function __construct(DirectoryWriter $directoryWriter, TrackWriter $trackWriter)
    {
        $this->directoryWriter = $directoryWriter;
        $this->trackWriter = $trackWriter;
    }

    public static function factory(Container $container): WriterFacade
    {
        $directoryWriter = new DirectoryWriter(
            $container->get(DirectoryRepository::class),
        );

        $trackWriter = new TrackWriter(
            $container->get(TrackService::class),
            $container->get(TrackSearchService::class),
            $container->get(BackendClient::class),
        );

        return new self($directoryWriter, $trackWriter);
    }

    /**
     * Zapisuje element kolekcji
     *
     * @param Directory|Track|CollectionItemInterface $collectionItem
     * @return Directory|Track|CollectionItemInterface
     */
    public function save(CollectionItemInterface $collectionItem): CollectionItemInterface
    {
        if ($collectionItem instanceof Directory) {
            return $this->directoryWriter->save($collectionItem);
        }

        assert($collectionItem instanceof Track);

        return $this->trackWriter->save($collectionItem);
    }
}
