<?php

namespace Assistant\Module\Track\Controller;

use Assistant\Controller as BaseController;
use Assistant\Module\Common;
use Assistant\Module\Track;

class TrackController extends BaseController
{
    use Common\Extension\Traits\GetPathBreadcrumbs;

    public function index($guid)
    {
        $track = (new Track\Repository\TrackRepository($this->app->container['db']))->findOneByGuid($guid);

        if ($track === null) {
            $this->app->redirect(
                sprintf('%s?query=%s', $this->app->urlFor('search.simple.index'), str_replace('-', ' ', $guid)),
                404
            );
        }

        return $this->app->render(
            '@track/index.twig',
            [
                'menu' => 'track',
                'track' => $track,
                'keyInfo' => $this->getTrackKeyInfo($track),
                'pathBreadcrumbs' => $this->getPathBreadcrumbs(dirname($track->pathname)),
                'similarTracks' => $this->getSimilarTracks($track),
            ]
        );
    }

    /**
     * Zwraca utwory podobne do podanego
     *
     * @param Track\Model\Track $baseTrack
     * @return array
     */
    private function getSimilarTracks(Track\Model\Track $baseTrack)
    {
        $similarity = new Track\Extension\Similarity(
            new Track\Repository\TrackRepository($this->app->container['db']),
            $this->app->container->parameters['track']['similarity']
        );

        return $similarity->getSimilarTracks($baseTrack);
    }

    /**
     * Zwraca klucze podobne do klucza podanego utworu
     *
     * @param Track\Model\Track $track
     * @return array|null
     */
    private function getTrackKeyInfo(Track\Model\Track $track)
    {
        $keyTools = new Common\Extension\KeyTools();

        if ($track->initial_key === null) {
            return null;
        }

        return [
            'relativeMinorToMajor' => [
                'title' => sprintf('To %s', $keyTools->getKeyMode($track->initial_key) ? 'minor' : 'major'),
                'value' => $keyTools->relativeMinorToMajor($track->initial_key),
            ],
            'perfectFourth' => [
                'title' => 'Perfect fourth',
                'value' => $keyTools->perfectFourth($track->initial_key),
            ],
            'perfectFifth' => [
                'title' => 'Perfect fifth',
                'value' => $keyTools->perfectFifth($track->initial_key),
            ],
            'minorThird' => [
                'title' => 'Minor third',
                'value' => $keyTools->minorThird($track->initial_key),
            ],
            'halfStep' => [
                'title' => 'Half step',
                'value' => $keyTools->halfStep($track->initial_key),
            ],
            'wholeStep' => [
                'title' => 'Whole step',
                'value' => $keyTools->wholeStep($track->initial_key),
            ],
            'dominantRelative' => [
                'title' => 'Dominant relative',
                'value' => $keyTools->dominantRelative($track->initial_key),
            ],
        ];
    }
}
