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

            $similarity = new Track\Extension\Similarity(
                new Track\Repository\TrackRepository($this->app->container['db']),
                $this->app->container->parameters['track']['similarity']
            );

            $previousTrack = null;

            foreach ($listing as $trackName) {
                if (($track = $this->getTrackByName($trackName))) {
                    $tracks[] = [
                        'track' => $track,
                        'similarityValue' => $previousTrack !== null
                            ? $similarity->getSimilarityValue($previousTrack, $track)
                            : null
                    ];

                    $previousTrack = $track;
                }
            }

            $matrix = [];

            foreach ($tracks as $track) {
                $row = [
                    'track' => $track['track'],
                    'tracks' => [ ],
                ];

                foreach ($tracks as $track2) {
                    $row['tracks'][$track2['track']->guid] = [
                        'track' => $track2['track'],
                        'similarityValue' => $track['track']->guid !== $track2['track']->guid
                            ? $similarity->getSimilarityValue($track['track'], $track2['track'])
                            : null
                    ];
                }

                $matrix[$track['track']->guid] = $row;
            }
        }

        return $this->app->render(
            '@mix/index.twig',
            [
                'menu' => 'mix',
                'listing' => $request->post('listing'),
                'tracks' => !empty($tracks) ? $tracks : [ ],
                'matrix' => !empty($matrix) ? $matrix : [ ],
                'mix' => !empty($matrix) ? $this->rearrange($matrix) : [ ],
            ]
        );
    }

    private function getTrackByName($name)
    {
        $trimmedName = trim($name);

        if (empty($trimmedName)) {
            return null;
        }

        $query = new \MongoRegex('/' . $trimmedName . '/i');
        $guidQuery = new \MongoRegex('/' . (new Slugify())->slugify($trimmedName) . '/i');

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
