<?php

namespace Assistant\Module\Collection\Extension\Validator;

use Assistant\Module\Collection\Extension\Validator\Exception\DuplicatedElementException;
use Assistant\Module\Collection\Extension\Validator\Exception\EmptyMetadataException;
use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Model\ModelInterface;
use Assistant\Module\File\Extension\SplFileInfo;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;

/**
 * Walidator elementów będących plikami
 */
class TrackValidator implements ValidatorInterface
{
    private TrackRepository $repository;

    private Id3Adapter $id3Adapter;

    private string $collectionRootDirectory;

    public function __construct(TrackRepository $repository, Id3Adapter $id3Adapter, string $collectionRootDirectory)
    {
        $this->repository = $repository;
        $this->id3Adapter = $id3Adapter;
        $this->collectionRootDirectory = $collectionRootDirectory;
    }

    /**
     * Waliduje plik (utwór muzyczny) pod kątem możliwości dodania go do bazy danych
     *
     * @param Track|ModelInterface $track
     * @return void
     */
    public function validate(ModelInterface $track): void
    {
        /* @var $indexedTrack Track */
        $indexedTrack = $this->repository->findOneBy([ 'pathname' => $track->pathname ]);

        if ($indexedTrack !== null && $track->metadata_md5 === $indexedTrack->metadata_md5) {
            throw new DuplicatedElementException(
                sprintf('Track "%s" is already in database.', $track->guid)
            );
        }

        // TODO: plik powinien zawierać całą ścieżkę
        // TODO: $track jako model powinien mieć metodę $track->getNode() / getFile()
        $fullTrackPathname = $this->collectionRootDirectory . $track->pathname;
        $file = new SplFileInfo($fullTrackPathname, ltrim($track->pathname, DIRECTORY_SEPARATOR));

        $metadata = $this->id3Adapter
            ->setFile($file)
            ->readId3v2Metadata();

        if (isset($metadata['artist']) === false || isset($metadata['title']) === false) {
            throw new EmptyMetadataException(sprintf('Track %s does\'t contains metadata.', $file->getBasename()));
        }

        // TODO: tutaj, w przyszłości, powinna zawarta być także logika odpowiedzialna za wyszukiwanie
        //       niekonsekwencji w metadanych
    }
}
