<?php

namespace Assistant\Module\Collection\Extension\Validator;

use Assistant\Module\Collection\Extension\Validator\Exception\DuplicatedElementException;
use Assistant\Module\Collection\Extension\Validator\Exception\EmptyMetadataException;
use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use Assistant\Module\Common\Model\ModelInterface;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use SplFileInfo;

/**
 * Walidator elementów będących plikami
 */
class TrackValidator implements ValidatorInterface
{
    private TrackRepository $repository;

    private Id3Adapter $id3Adapter;

    public function __construct(TrackRepository $repository, Id3Adapter $id3Adapter)
    {
        $this->repository = $repository;
        $this->id3Adapter = $id3Adapter;
    }

    /**
     * Weryfikuje czy plik (utwór muzyczny) może zostać dodany do bazy danych kolekcji
     *
     * @param Track|ModelInterface $track
     * @return void
     */
    public function validate(ModelInterface $track): void
    {
        /* @var $indexedTrack Track */
        $indexedTrack = $this->repository->findOneBy([ 'pathname' => $track->getPathname() ]);

        if ($indexedTrack !== null && $track->getMetadataMd5() === $indexedTrack->getMetadataMd5()) {
            throw new DuplicatedElementException(
                sprintf('Track "%s" is already in database.', $track->getGuid())
            );
        }

        $metadata = $this->id3Adapter
            ->setFile($track->getFile())
            ->readId3v2Metadata();

        $hasValidMetadata = $this->validateMetadata($metadata);

        if (!$hasValidMetadata) {
            $message = sprintf('Track %s does\'t contains metadata.', $track->getFile()->getBasename());

            throw new EmptyMetadataException($message);
        }

        // TODO: tutaj, w przyszłości, powinna zawarta być także logika odpowiedzialna za wyszukiwanie
        //       niekonsekwencji w metadanych
    }

    /**
     * @todo Rzucać wyjątek zawierający tablicę z błędami lub zwracać [ bool $isValid, array $errors ]
     *
     * @param array $metadata
     * @return bool
     */
    private function validateMetadata(array $metadata): bool
    {
        $withoutArtist = empty($metadata['artist']);
        $withoutTitle = empty($metadata['title']);
        $withoutGenre = empty($metadata['genre']);
        $withoutInitialKey = empty($metadata['initial_key']);
        $withoutBpm = empty($metadata['bpm']);

        // @TODO: Sprawdzać spacje na końcu i początku. Co jeszcze? (przypomnieć sobie najczęstsze problemy)

        return !$withoutArtist && !$withoutTitle && !$withoutInitialKey && !$withoutBpm && !$withoutGenre;
    }
}
