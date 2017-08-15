<?php

namespace Assistant\Module\Track\Controller;

use Assistant\Module\Common\Controller\AbstractController;
use Assistant\Module\Common;
use Assistant\Module\Track;

class TrackController extends AbstractController
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

        $form = $this->app->request->get('similarity');

        return $this->app->render(
            '@track/index.twig',
            [
                'menu' => 'track',
                'track' => $track->toArray(),
                'keyInfo' => $this->getTrackKeyInfo($track),
                'pathBreadcrumbs' => $this->getPathBreadcrumbs(dirname($track->pathname)),
                'form' => $form,
                'similarTracks' => $this->getSimilarTracks(
                    $track,
                    $this->app->container->parameters['track']['similarity'],
                    $form
                ),
            ]
        );
    }

    /**
     * Zwraca utwory podobne do podanego utworu
     *
     * @param Track\Model\Track $track
     * @param \Slim\Http\Request $request
     * @return array
     */
    private function getSimilarTracks($baseTrack, $baseParameters, $customParameters)
    {
        $track = $baseTrack;
        $parameters = $baseParameters;

        if (!empty($customParameters['track'])) {
            $track = $track->set($customParameters['track']);
        }

        if (!empty($customParameters['providers']['names'])) {
            $parameters['providers']['names'] = $customParameters['providers']['names'];
        }

        $similarity = new Track\Extension\Similarity(
            new Track\Repository\TrackRepository($this->app->container['db']),
            $parameters
        );

        return $similarity->getSimilarTracks($track);
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
