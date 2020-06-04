<?php

namespace Assistant\Module\Mix\Controller;

use Assistant\Module\Common\Controller\AbstractController;
use Assistant\Module\Mix\Extension\ArrangedMix;
use Assistant\Module\Mix\Extension\Strategy\MostSimilarTrackStrategy;
use Assistant\Module\Track\Extension\Similarity;
use Assistant\Module\Track\Model\Track;
use Assistant\Module\Track\Repository\TrackRepository;
use Cocur\Slugify\Slugify;
use Cocur\Slugify\SlugifyInterface;
use MongoDB\BSON\Regex;
use Slim\Helper\Set as Container;

class MixController extends AbstractController
{
    public function index()
    {
        $request = $this->app->request();

        if ($request->isPost()) {
            $listing = explode(PHP_EOL, $request->post('listing'));

            [ $mix, $matrix ] = self::getMixInfo($this->app->container, $listing);
        }

        return $this->app->render('@mix/index.twig', [
            'menu' => 'mix',
            'form' => $request->post(),
            'matrix' => $matrix ?? [],
            'mix' => $mix ?? [],
        ]);
    }

    /**
     * @param Container $container
     * @param string[] $listing
     * @return array
     */
    private static function getMixInfo(Container $container, array $listing): array
    {
        $repository = new TrackRepository($container['db']);
        $similarity = new Similarity($repository, $container['parameters']['track']['similarity']);

        $strategy = new MostSimilarTrackStrategy($similarity);
        $tracks = self::getTracks($similarity, $repository, new Slugify(), $listing);

        //@todo: dodać strategię, która dobierze najlepszy pierwszy kawałek dla MostSimilarTrackStrategy
        //@todo: dodać strategię, która dobierze najbardziej podobny następny kawałek (także do kolejnego),
        //       jeśli najlepiej różnica do następnego będzie większa od zadanej

        $arrangedMix = new ArrangedMix($strategy, $tracks);
        $mix = $arrangedMix->getMix();
        $matrix = $arrangedMix->getMatrix();

        return [ $mix, $matrix ];
    }

    /**
     * @todo: Wydzielić do osobnej klasy (łącznie z getTrackByName)
     *
     * @param Similarity $similarity
     * @param TrackRepository $repository
     * @param SlugifyInterface $slugify
     * @param array $listing
     * @return array
     */
    private static function getTracks(Similarity $similarity, TrackRepository $repository, SlugifyInterface $slugify, array $listing): array
    {
        $tracks = [];

        $previousTrack = null;

        foreach ($listing as $trackName) {
            $track = self::getTrackByName($repository, $slugify, $trackName);

            if (!$track) {
                // @todo: brak wyszukanego utworu powinien być komunikowany na froncie
                continue;
            }

            $tracks[] = [
                'track' => $track,
                'similarityValue' => $previousTrack ? $similarity->getSimilarityValue($previousTrack, $track) : null
            ];

            $previousTrack = $track;
        }

        return $tracks;
    }

    private static function getTrackByName(TrackRepository $repository, SlugifyInterface $slugify, string $name): ?Track
    {
        $trimmedName = trim($name);

        if (empty($trimmedName)) {
            return null;
        }

        $query = new Regex($trimmedName, 'i');
        $guidQuery = new Regex($slugify->slugify($trimmedName), 'i');

        $track = $repository->findOneBy([
            '$or' => [
                [ 'artist' => $query ],
                [ 'title' => $query ],
                [ 'guid' => $guidQuery ],
            ]
        ]);

        /** @var Track|null $track */
        return $track;
    }
}
