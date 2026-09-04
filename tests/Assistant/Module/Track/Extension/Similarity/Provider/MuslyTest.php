<?php

namespace Assistant\Module\Track\Extension\Similarity\Provider;

use Assistant\Module\Common\Extension\SimilarTracksCollection\SimilarTracksCollectionService;
use Assistant\Module\Track\Extension\Similarity\DeprecationGuard;
use Assistant\Module\Track\Extension\Similarity\TrackFactory;
use Assistant\Module\Track\Model\Track;
use Musly\Exception\RuntimeException as MuslyException;
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
            ->willReturn($this->neighbourList($secondTrack));

        $provider = new Musly($this->createService($library));

        // sekwencja a -> a -> b -> a wymaga dokładnie dwóch wywołań
        $provider->getSimilarityValue($firstTrack, $secondTrack);
        $provider->getSimilarityValue($firstTrack, $secondTrack);
        $provider->getSimilarityValue($secondTrack, $firstTrack);
        $provider->getSimilarityValue($firstTrack, $secondTrack);
    }

    /**
     * Odległości po normalizacji Mutual Proximity są ściśnięte w okolicach zera, więc wartość wynika
     * z pozycji na liście, a nie z samej odległości.
     */
    public function testSimilarityValueIsCalculatedFromNeighbourPosition(): void
    {
        $baseTrack = TrackFactory::create(guid: 'base-track');
        $comparedTrack = TrackFactory::create(guid: 'compared-track');

        $library = $this->createMock(MuslyLibrary::class);
        $library->method('getSimilarTracks')->willReturn($this->neighbourList(
            TrackFactory::create(guid: 'nearest-track'),
            $comparedTrack,
            TrackFactory::create(guid: 'third-track'),
            TrackFactory::create(guid: 'fourth-track'),
        ));

        $provider = new Musly($this->createService($library));

        // druga pozycja z czterech: 100 * (1 - 1/4)
        self::assertSame(75, $provider->getSimilarityValue($baseTrack, $comparedTrack));
    }

    public function testTrackOutsideNeighbourListGivesZero(): void
    {
        $baseTrack = TrackFactory::create(guid: 'base-track');
        $comparedTrack = TrackFactory::create(guid: 'compared-track');

        $library = $this->createMock(MuslyLibrary::class);
        $library->method('getSimilarTracks')->willReturn(
            $this->neighbourList(TrackFactory::create(guid: 'other-track')),
        );

        $provider = new Musly($this->createService($library));

        self::assertSame(0, $provider->getSimilarityValue($baseTrack, $comparedTrack));
    }

    public function testCandidatePathnamesComeFromNeighbourList(): void
    {
        $baseTrack = TrackFactory::create(guid: 'base-track');
        $nearestTrack = TrackFactory::create(guid: 'nearest-track');

        $library = $this->createMock(MuslyLibrary::class);
        $library->method('getSimilarTracks')->willReturn($this->neighbourList($nearestTrack));

        $provider = new Musly($this->createService($library));

        self::assertSame([ $nearestTrack->getPathname() ], $provider->getCandidatePathnames($baseTrack));
    }

    /**
     * Niedostępna lista oznacza brak danych, a nie zero: awaria wyłącza składnik Musly z wyniku,
     * zamiast obniżać go dla wszystkich par. Nieudana próba jest pamiętana, żeby jedna awaria
     * nie mnożyła wywołań zewnętrznego procesu.
     */
    public function testUnavailableNeighbourListGivesNoValue(): void
    {
        $baseTrack = TrackFactory::create(guid: 'base-track');
        $comparedTrack = TrackFactory::create(guid: 'compared-track');

        $library = $this->createMock(MuslyLibrary::class);
        $library
            ->expects(self::once())
            ->method('getSimilarTracks')
            ->willThrowException(new MuslyException('collection not available'));

        $provider = new Musly($this->createService($library));

        // provider zgłasza błąd przez Kint, którego wydruk nie jest częścią testowanego zachowania
        ob_start();

        try {
            $similarityValue = $provider->getSimilarityValue($baseTrack, $comparedTrack);
            $repeatedSimilarityValue = $provider->getSimilarityValue($baseTrack, $comparedTrack);
            $candidatePathnames = $provider->getCandidatePathnames($baseTrack);
        } finally {
            ob_end_clean();
        }

        self::assertNull($similarityValue);
        self::assertNull($repeatedSimilarityValue);
        self::assertSame([], $candidatePathnames);
    }

    /** @return array<int, array<string, string>> */
    private function neighbourList(Track ...$tracks): array
    {
        $neighbours = [];

        foreach ($tracks as $index => $track) {
            $neighbours[] = [
                'track-origin' => $track->getPathname(),
                'track-distance' => sprintf('0.0%d', $index + 1),
            ];
        }

        return $neighbours;
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
