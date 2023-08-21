<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Common\Extension\Beatport\BeatportTrackBuilder;
use Assistant\Module\Common\Extension\BeatportApiClient;
use Assistant\Module\Common\Extension\Config;
use Assistant\Module\Common\Extension\TrackSearch\BeatportSearchTracks;
use Assistant\Module\Common\Extension\TrackSearch\BeatportUniqueTracks;
use Assistant\Module\Common\Extension\TrackSearch\EmptySearchTracks;
use Assistant\Module\Common\Extension\TrackSearch\GoogleBeatportSearchTracks;
use Assistant\Module\Common\Extension\TrackSearch\GoogleSearchApiClient;

/**
 *
 * Wszystko, co się tutaj dzieje, ma związek z kawałkami z Beatport-u, więc trzeba jeszcze przemyśleć
 * jak nazwać / podzielić tę klasę.
 *
 * Poza powyższym zastanowić nad rozdzieleniem niniejszej klasy na więcej klas:
 * - budującą obiekty klas, które wyszukują kawałki,
 * - klasę, która wyszukuje kawałki,
 * - klasę odpowiedzialną za budowanie sugestii na podstawie znalezionych utworów
 *
 * @fixme Przy problemach z połączeniem wywala się cała aplikacja, poprawić.
 * @todo Dodać obsługę generowanie sugestii z metadanych pliku oraz nazwy pliku
 * @todo Część klas (m.in. BeatportApiClient) przerzucić do DI dla wygodniejszej inicjalizacji
 */
final readonly class BeatportTrackMetadataSuggestionsService
{
    private TrackMetadataSuggestionsBuilder $suggestionsBuilder;

    private BeatportSearchTracks|EmptySearchTracks $beatportSearchTracks;
    private GoogleBeatportSearchTracks|EmptySearchTracks $googleBeatportSearchTracks;

    public function __construct(Config $config)
    {
        $beatportApiClient = BeatportApiClient::create($config);
        $beatportTrackBuilder = new BeatportTrackBuilder($beatportApiClient);

        if ($config->get(self::class . '.' . BeatportSearchTracks::class . '.enabled')) {
            $this->beatportSearchTracks = new BeatportSearchTracks($beatportApiClient, $beatportTrackBuilder);
        } else {
            $this->beatportSearchTracks = new EmptySearchTracks();
        }

        if ($config->get(self::class . '.' . GoogleBeatportSearchTracks::class . '.enabled')) {
            $googleSearchApiConfig = $config->get(self::class . '.' . GoogleSearchApiClient::class);

            $googleSearchApiClient = new GoogleSearchApiClient(
                $googleSearchApiConfig['api_key'],
                $googleSearchApiConfig['search_id'],
            );

            $this->googleBeatportSearchTracks = new GoogleBeatportSearchTracks(
                $googleSearchApiClient,
                $beatportTrackBuilder
            );
        } else {
            $this->googleBeatportSearchTracks = new EmptySearchTracks();
        }

        $this->suggestionsBuilder = new TrackMetadataSuggestionsBuilder();
    }

    public function get(string $query): array
    {
        // Roboczo. $query powinno być elastyczne, być jakimś zapytaniem, co
        // pozwoli na wyszukiwanie po np. beatport id, beatport url, itp
        // Przykładowe formaty query:
        // - query:joris voorn messiah dark science dub mix,
        // - url:https://www.beatport.com/track/it-goes-like-nanana/17839150, albo beatport_url, aby nie zgadywać,
        // - bid:12857620, albo beatport_id:12857620
        // itd.

        // --

        // $query = 'Jan Blomqvist, Aparde Drift feat. Aparde Eelke Kleijn Remix';
        // $query = 'Messiah (feat. HÆLOS) Dark Science Dub Mix';
        // $query = 'This Game Feat. Bertie Blackman Eleven Remix';

        // --- wyszukiwanie utworów

        try {
            $tracksFoundByGoogleSearch = ($this->googleBeatportSearchTracks)($query);
            $tracksFoundByBeatportSearch = ($this->beatportSearchTracks)($query);
        } catch (\Throwable $e) {
            $tracksFoundByBeatportSearch = [];
            $tracksFoundByGoogleSearch = [];

            var_dump($e->getMessage());
        }

        $beatportTracks = (new BeatportUniqueTracks())
            ->add(...$tracksFoundByBeatportSearch)
            ->add(...$tracksFoundByGoogleSearch)
            ->get();

        if (!$beatportTracks) {
            return [];
        }

        // -- budowanie sugestii na podstawie znalezionych utworów

        $suggestions = array_map(
            fn ($beatportTrack) => [
                'track' => $beatportTrack,
                'suggestions' => $this->suggestionsBuilder->fromBeatportTrack($beatportTrack),
            ],
            $beatportTracks
        );

        /*
        foreach ($beatportTracks as $beatportTrack) {
            // var_dump($beatportTrack);
            $artists = implode(', ', $beatportTrack->artists);
            $title = $beatportTrack->title;
            $id = $beatportTrack->title;
            $suggestions = $this->suggestionsBuilder->fromBeatportTrack($beatportTrack);
            echo "<b>{$artists} - $title [$id]</b><pre>";
            var_dump($suggestions);
            echo '</pre><hr>';
        }

        exit;
        */

        return $suggestions;
    }
}
