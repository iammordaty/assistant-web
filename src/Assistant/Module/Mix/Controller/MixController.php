<?php

namespace Assistant\Module\Mix\Controller;

use Assistant\Module\Common\Controller\AbstractController;
use Assistant\Module\Track;

use Cocur\Slugify\Slugify;

class MixController extends AbstractController
{
    public function index()
    {
        $request = $this->app->request();
        
        if ($request->isPost()) {
            $tracks = [];

            $listing = explode(PHP_EOL, $request->post('listing'));

            foreach ($listing as $trackName) {
                if (($track = $this->getTrackByName($trackName))) {
                    $tracks[] = $track;
                }
            }

            $similarity = new Track\Extension\Similarity(
                new Track\Repository\TrackRepository($this->app->container['db']),
                $this->app->container->parameters['track']['similarity']
            );

            $matrix = [];

            foreach ($tracks as $track) {
                $row = [
                    'track' => $track,
                    'tracks' => [ ],
                ];

                foreach ($tracks as $track2) {
                    $row['tracks'][$track2->guid] = [
                        'track' => $track2,
                        'similarityValue' => $track->guid !== $track2->guid
                            ? $similarity->getSimilarityValue($track, $track2)
                            : null
                    ];
                }

                $matrix[$track->guid] = $row;
            }
        }

        return $this->app->render(
            '@mix/index.twig',
            [
                'menu' => 'mix',
                'listing' => $request->post('listing'),
                'tracks' => isset($tracks) ? $tracks : [],
                'matrix' => isset($matrix) ? $matrix : [],
                'mix' => isset($matrix) ? $this->rearrange($matrix) : [],
            ]
        );
    }

    private function getTrackByName($name)
    {
        $query = new \MongoRegex('/' . trim($name) . '/i');
        $guidQuery = new \MongoRegex('/' . trim((new Slugify())->slugify($name)) . '/i');

        return (new Track\Repository\TrackRepository($this->app->container['db']))->findOneBy(
            [
                '$or' => [
                    [ 'artist' => $query ],
                    [ 'title' => $query ],
                    [ 'guid' => $guidQuery ],
                ]
            ]
        );
    }

    private function rearrange(array $matrix)
    {
        $track = reset($matrix);

        $sorted = [ $track ];
        $this->remove($track, $matrix);

        do {
            $track = $this->getMostSimilarTrack($matrix[$track['track']->guid]['tracks']);

            if ($track !== null) {
                $this->remove($track, $matrix);
                $sorted[] = $track;
            }

        } while (!empty($track['track']));

        return $sorted;
    }

    private function getMostSimilarTrack(array $tracks)
    {
        $mostSimilar = null;

        foreach ($tracks as $track) {
            if ($track['similarityValue'] > $mostSimilar['similarityValue']) {
                $mostSimilar = $track;
            }
        }

        return $mostSimilar;
    }

    private function remove($track, &$matrix)
    {
        foreach ($matrix as &$row) {
            unset($row['tracks'][$track['track']->guid]);
        }
    }
}