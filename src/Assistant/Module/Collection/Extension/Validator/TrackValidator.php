<?php

namespace Assistant\Module\Collection\Extension\Validator;

use Assistant\Module\Collection\Extension\Validator\Exception\DuplicatedElementException;
use Assistant\Module\Collection\Extension\Validator\Exception\InvalidMetadataException;
use Assistant\Module\Collection\Extension\Validator\TrackValidator\TrackMetadataValidator;
use Assistant\Module\Collection\Model\CollectionItemInterface;
use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Track\Extension\TrackService;
use Assistant\Module\Track\Model\IncomingTrack;
use Assistant\Module\Track\Model\Track;
use KeyTools\KeyTools;
use Psr\Container\ContainerInterface;

/** Walidator elementów będących plikami */
final readonly class TrackValidator implements ValidatorInterface
{
    private function __construct(
        private TrackService $trackService,
        private SimilarTracksCollectionService $similarTracksCollectionService,
        private TrackMetadataValidator $metadataValidator,
    ) {
    }

    public static function factory(ContainerInterface $container): self
    {
        return new self(
            $container->get(TrackService::class),
            $container->get(SimilarTracksCollectionService::class),
            new TrackMetadataValidator(KeyTools::NOTATION_KEYS_CAMELOT_KEY),
        );
    }

    /** Weryfikuje czy plik (utwór muzyczny) może zostać dodany do bazy danych kolekcji */
    public function validate(CollectionItemInterface $collectionItem): void
    {
        /** @var IncomingTrack|Track $track */
        $track = $collectionItem;

        $isTrackDuplicated = $this->isTrackDuplicated($track);

        if ($isTrackDuplicated) {
            $message = sprintf('Track "%s" is already in database.', $track->getName());

            throw new DuplicatedElementException($message);
        }

        // @idea: warto jeszcze dorzucić sprawdzanie bitrate-u (cbr, 320 kbps)

        $result = ($this->metadataValidator)($track);

        if (!$result->isValid) {
            $message = sprintf(
                'Track %s does not contain metadata or it is invalid.',
                $track->getFile()->getBasename()
            );

            throw new InvalidMetadataException($message, $track->getFile(), $result->errors);
        }
    }

    private function isTrackDuplicated(Track $track): bool
    {
        $existingTrack = $this->trackService->getByPathname($track->getPathname());

        $hasSameArtists = $track->getArtists() === $existingTrack?->getArtists();
        $hasSameMetadata = $track->getMetadataMd5() === $existingTrack?->getMetadataMd5();
        $isInSimilarTracksCollection = $this->similarTracksCollectionService->hasTrack($track->getFile());

        return $hasSameMetadata && $hasSameArtists && $isInSimilarTracksCollection;
    }
}
