<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Track\Extension\Similarity\DeprecationGuard;
use Assistant\Module\Track\Extension\Similarity\TrackFactory;
use Musly\Musly as MuslyLibrary;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

require_once __DIR__ . '/../DeprecationGuard.php';
require_once __DIR__ . '/../TrackFactory.php';

final class MuslyTest extends TestCase
{
    use DeprecationGuard;

    /**
     * Pobranie listy sąsiadów to wywołanie zewnętrznego procesu. Wcześniej lista była pamiętana
     * dla pierwszego utworu bazowego, więc siatka podobieństwa miksu liczyła dalsze wiersze
     * względem złego utworu.
     */
    public function testNeighbourListIsRetrievedOncePerBaseTrack(): void
    {
        $firstTrack = TrackFactory::create(guid: 'first-track');
        $secondTrack = TrackFactory::create(guid: 'second-track');

        $library = $this->createMock(MuslyLibrary::class);
        $library
            ->expects(self::exactly(2))
            ->method('getSimilarTracks')
            ->willReturn([
                [ 'track-origin' => $secondTrack->getPathname(), 'track-distance' => '0.0153444' ],
            ]);

        $provider = new Musly($this->createService($library));

        // sekwencja a -> a -> b -> a wymaga dokładnie dwóch wywołań
        $provider->getSimilarityValue($firstTrack, $secondTrack);
        $provider->getSimilarityValue($firstTrack, $secondTrack);
        $provider->getSimilarityValue($secondTrack, $firstTrack);
        $provider->getSimilarityValue($firstTrack, $secondTrack);
    }

    public function testSimilarityValueIsCalculatedFromDistance(): void
    {
        $baseTrack = TrackFactory::create(guid: 'base-track');
        $comparedTrack = TrackFactory::create(guid: 'compared-track');

        $library = $this->createMock(MuslyLibrary::class);
        $library->method('getSimilarTracks')->willReturn([
            [ 'track-origin' => $comparedTrack->getPathname(), 'track-distance' => '0.0153444' ],
        ]);

        $provider = new Musly($this->createService($library));

        // 100 - 0,0153444 * 100 = 98,47, obcięte do liczby całkowitej
        self::assertSame(98, $provider->getSimilarityValue($baseTrack, $comparedTrack));
    }

    public function testTrackOutsideNeighbourListGivesZero(): void
    {
        $baseTrack = TrackFactory::create(guid: 'base-track');
        $comparedTrack = TrackFactory::create(guid: 'compared-track');
        $otherTrack = TrackFactory::create(guid: 'other-track');

        $library = $this->createMock(MuslyLibrary::class);
        $library->method('getSimilarTracks')->willReturn([
            [ 'track-origin' => $otherTrack->getPathname(), 'track-distance' => '0.0153444' ],
        ]);

        $provider = new Musly($this->createService($library));

        self::assertSame(0, $provider->getSimilarityValue($baseTrack, $comparedTrack));
    }

    /**
     * Konstruktor serwisu inicjalizuje kolekcję na dysku, a sama klasa jest finalna i nie da się jej
     * zastąpić atrapą, dlatego instancja powstaje bez konstruktora, z podstawioną biblioteką.
     */
    private function createService(MuslyLibrary $library): SimilarTracksCollectionService
    {
        $reflection = new ReflectionClass(SimilarTracksCollectionService::class);

        /** @var SimilarTracksCollectionService $service */
        $service = $reflection->newInstanceWithoutConstructor();

        $reflection->getProperty('musly')->setValue($service, $library);

        return $service;
    }
}
