<?php

namespace Assistant\Module\Track\Controller\Track;

use Assistant\Module\Common\Extension\Backend\Client as BackendClient;
use Assistant\Module\Common\Extension\Beatport\BeatportTrackBuilder;
use Assistant\Module\Common\Extension\BeatportApiClient;
use Assistant\Module\Common\Extension\TrackSearch\BeatportSearchTracks;
use Assistant\Module\Common\Extension\TrackSearch\BeatportUniqueTracks;
use Assistant\Module\Common\Extension\TrackSearch\GoogleBeatportSearchTracks;
use Assistant\Module\Common\Extension\TrackSearch\GoogleSearchApiClient;
use Assistant\Module\Track\Extension\TrackMetadataSuggestionsBuilder;
use Slim\Helper\Set as Container;

// Wszystko co się tutaj dzieje ma związek z kawałkami z Beatportu, więc trzeba
// jeszcze lekko przemyśleć jak nazwać / podzielić tę klasę
final class TrackMetadataSuggestions
{
    public static function factory(Container $container): self
    {
        return new self();
    }

    /**
     * @todo Obiekty (które?) do konstruktora
     *
     * @param string $query
     * @return array
     */
    public function get(string $query): array
    {
        // Roboczo. $trackName powinno być elastyczne, być jakimś zapytaniem, co
        // pozwoli na wyszukiwanie po np. beatport id, beatport url, itp
        // Przykładowe formaty query:
        // - query:joris voorn messiah dark science dub mix
        // - url:https://www.beatport.com/track/messiah-feat-h-los-dark-science-dub-mix/12857620, albo beatport_url, aby nie zgadywać
        // - bid:12857620, albo beatport_id:12857620
        // itd

        // --

        // $query = 'Jan Blomqvist, Aparde Drift feat. Aparde Eelke Kleijn Remix';
        // $query = 'Messiah (feat. HÆLOS) Dark Science Dub Mix';
        // $query = 'This Game Feat. Bertie Blackman Eleven Remix';

        // --- tworzenie obiektów wyszukujących

        $oauthParams = [
            'consumer_key' => $_ENV['BEATPORT_API_CONSUMER_KEY'],
            'consumer_secret' => $_ENV['BEATPORT_API_CONSUMER_SECRET'],
            'username' => $_ENV['BEATPORT_API_USERNAME'],
            'password' => $_ENV['BEATPORT_API_PASSWORD'],
        ];
        $beatportApiClient = BeatportApiClient::create($oauthParams, BASE_DIR);
        $beatportTrackBuilder = new BeatportTrackBuilder($beatportApiClient);

        $googleSearchApiClient = new GoogleSearchApiClient(
            $_ENV['GOOGLE_SEARCH_API_KEY'],
            $_ENV['GOOGLE_SEARCH_API_SEARCH_ID'],
        );

        $beatportSearchTracks = new BeatportSearchTracks($beatportApiClient, $beatportTrackBuilder);
        $googleBeatportSearchTracks = new GoogleBeatportSearchTracks($googleSearchApiClient, $beatportTrackBuilder);

        // --- wyszukiwanie utworów

        // to jako tablica, którą w foreachu wywołuję się poprzez invoke z query jako parametrem?
        $tracksFoundByBeatportSearch = $beatportSearchTracks($query);
        $tracksFoundByGoogleSearch = $googleBeatportSearchTracks($query);
        // var_dump($tracksFoundByGoogleSearch);exit;

        $beatportTracks = (new BeatportUniqueTracks())
            ->add($tracksFoundByBeatportSearch)
            ->add($tracksFoundByGoogleSearch)
            ->get();

        // foreach ($beatportTracks as $beatportTrack) { var_dump($beatportTrack); } exit;

        if (!$beatportTracks) {
            return [];
        }

        // -- budowanie sugestii na podstawie znalezionych utworów
        
        $suggestionsBuilder = new TrackMetadataSuggestionsBuilder(new BackendClient());

        $suggestions = array_map(
            static fn($beatportTrack) => [
                'track' => $beatportTrack->toArray(),
                'suggestions' => $suggestionsBuilder->fromBeatportTrack($beatportTrack)->toArray(),
            ],
            $beatportTracks
        );

        /*
        foreach ($beatportTracks as $beatportTrack) {
            // var_dump($beatportTrack);
            $artists = implode(', ', $beatportTrack->getArtists());
            $title = $beatportTrack->getTitle();
            $id = $beatportTrack->getId();
            $suggestions = $trackMetadataSuggestionsBuilder->fromBeatportTrack($beatportTrack);

            echo "<b>${artists} - $title [$id]</b><pre>";
            var_dump($suggestions->toArray());
            echo '</pre>';
            exit;
        }
        */

        return $suggestions;
    }
}