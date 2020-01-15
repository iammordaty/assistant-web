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

        $implode = function ($lines) {
            return implode($lines, '<br  />');
        };

        return [
            'relativeMinorToMajor' => [
                'title' => sprintf('To %s', $keyTools->getKeyMode($track->initial_key) ? 'minor' : 'major'),
                'value' => $keyTools->relativeMinorToMajor($track->initial_key),
                'description' => $implode([
                    'This combination will likely sound good because the notes of both scales are the same,',
                    'but the root note is different. The energy of the room will change dramatically.',
                ]),
            ],
            'perfectFourth' => [
                'title' => 'Perfect fourth',
                'value' => $keyTools->perfectFourth($track->initial_key),
                'description' => $implode([
                    'I like to say this type of mix will take the crowd deeper.',
                    'It won\'t raise the energy necessarily but will give your listeners goosebumps!',
                ]),
            ],
            'perfectFifth' => [
                'title' => 'Perfect fifth',
                'value' => $keyTools->perfectFifth($track->initial_key),
                'description' => 'This will raise the energy in the room.',
            ],
            'minorThird' => [
                'title' => 'Minor third',
                'value' => $keyTools->minorThird($track->initial_key),
                'description' => $implode([
                    'While these scales have 3 notes that are different,',
                    'I\'ve found that they still sound good played together',
                    'and tend to raise the energy of a room.',
                ]),
            ],
            'halfStep' => [
                'title' => 'Half step',
                'value' => $keyTools->halfStep($track->initial_key),
                'description' => $implode([
                    'While these two scales have almost no notes in common,',
                    'musically they shouldn’t sound good together but I\'ve found if you plan it right',
                    'and mix a percussive outro of one song with a percussive intro of another song,',
                    'and slowly bring in the melody this can have an amazing effect musically and',
                    'raise the energy of the room dramatically.',
                ]),
            ],
            'wholeStep' => [
                'title' => 'Whole step',
                'value' => $keyTools->wholeStep($track->initial_key),
                'description' => $implode([
                    'This will raise the energy of the room. I like to call it "hands in the air" mixing,',
                    'and others might call it "Energy Boost mixing".',
                ])
            ],
            'dominantRelative' => [
                'title' => 'Dominant relative',
                'value' => $keyTools->dominantRelative($track->initial_key),
                'description' => $implode([
                    'I\'ve found this is the best way to go from Major to Minor keys',
                    'and from Minor to Major because the scales only have one note difference',
                    'and the combination sounds great.',
                ])
            ],
        ];
    }
}
