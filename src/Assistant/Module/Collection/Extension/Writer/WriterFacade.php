<?php

namespace Assistant\Module\Collection\Extension\Writer;

use Assistant\Module\Collection\Model\CollectionItemInterface;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Directory\Extension\DirectoryService;
use Assistant\Module\Directory\Model\Directory;
use Assistant\Module\Search\Extension\TrackSearchService;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Model\Track;
use Psr\Container\ContainerInterface;

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

    public static function factory(ContainerInterface $container): self
    {
        $directoryWriter = new DirectoryWriter(
            $container->get(DirectoryService::class),
        );

        $trackWriter = new TrackWriter(
            $container->get(TrackService::class),
            $container->get(TrackSearchService::class),
            $container->get(SimilarTracksCollectionService::class),
        );

        return new self($directoryWriter, $trackWriter);
    }

    /** Zapisuje element kolekcji */
    public function save(CollectionItemInterface $collectionItem): CollectionItemInterface
    {
        if ($collectionItem instanceof Directory) {
            return $this->directoryWriter->save($collectionItem);
        }

        assert($collectionItem instanceof Track);

        return $this->trackWriter->save($collectionItem);
    }
}
