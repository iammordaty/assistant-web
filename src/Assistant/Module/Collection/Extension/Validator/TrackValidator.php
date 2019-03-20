<?php

namespace Assistant\Module\Collection\Extension\Validator;

use Assistant\Module\Collection\Extension\Validator\Exception\DuplicatedElementException;
use Assistant\Module\Collection\Extension\Validator\Exception\EmptyMetadataException;
use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\File\Extension\Parser as MetadataParser;
use Assistant\Module\File\Extension\SplFileInfo;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use MongoDB;

/**
 * Walidator elementów będących plikami
 */
class TrackValidator extends AbstractValidator
{
    /**
     * @var Id3Adapter
     */
    private $id3Adapter;

    /**
     * @var MetadataParser
     */
    private $metadataParser;

    /**
     * @var TrackRepository
     */
    private $repository;

    /**
     * {@inheritDoc}
     */
    public function __construct(MongoDB $db, array $parameters)
    {
        parent::__construct($db, $parameters);

        $this->repository = new TrackRepository($db);
        $this->id3Adapter = new Id3Adapter();
        $this->metadataParser = new MetadataParser($parameters['track']['metadata']['parser']);
    }

    /**
     * Waliduje plik (utwór muzyczny) pod kątem możliwości dodania go do bazy danych
     *
     * @param Track $track
     */
    public function validate($track)
    {
        /* @var $indexedTrack Track */
        $indexedTrack = $this->repository->findOneBy([ 'pathname' => $track->pathname ], [ 'metadata_md5' ]);

        if ($indexedTrack !== null && $track->metadata_md5 === $indexedTrack->metadata_md5) {
            throw new DuplicatedElementException(
                sprintf('Track "%s" is already in database.', $track->guid)
            );
        }

        // TODO: plik powienien zawierać całą ścieżkę
        // TODO: $track jako model powienien mieć metodę $track->getNode() / getFile()
        $fullTrackPathname = $this->parameters['collection']['root_dir'] . $track->pathname;
        $file = new SplFileInfo($fullTrackPathname, ltrim($track->pathname, DIRECTORY_SEPARATOR));

        $metadata = $this->id3Adapter
            ->setFile($file)
            ->readId3v2Metadata();

        if (isset($metadata['artist']) === false || isset($metadata['title']) === false) {
            throw new EmptyMetadataException(sprintf('Track %s does\'t contains metadata.', $node->getBasename()));
        }

        // TODO: tutaj, w przyszości, powinna zawarta być także logika odpowiedzialna za wyszukiwanie
        // niekonsekwencji w metadanych
    }
}
