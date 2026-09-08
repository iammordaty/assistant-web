<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Track\Model\IncomingTrack;
use Assistant\Module\Track\Model\Track;
use SplFileInfo;

/**
 * Jedyne miejsce, w którym rozpoznaje się układ nazwy pliku. Wynik jest wyłącznie podpowiedzią -
 * wstępnie zaznaczoną opcją, którą użytkownik widzi i może zmienić. Warstwa zapisu dostaje format
 * jawnie i niczego nie zgaduje: źle zgadnięta podpowiedź jest samonaprawialna, źle zgadnięty
 * zapis to cichy błąd na dysku.
 */
final class FilenameFormatSuggester
{
    public function __construct(private TrackLocationArbiter $locationArbiter)
    {
    }

    public function suggest(Track|IncomingTrack $track): FilenameFormat
    {
        $file = $track->getFile();

        if ($this->locationArbiter->getLocationKind($file) !== LocationKind::SINGLES) {
            return FilenameFormat::ARTIST_TITLE;
        }

        // plik już jest w jednym z dwóch układów Singles, więc jego obecna nazwa jest sygnałem
        // dokładnym - podpowiedź trafia w istniejący stan wydania
        return self::hasLeadingTrackNumber($file)
            ? FilenameFormat::MULTI_ARTIST_RELEASE
            : FilenameFormat::SINGLE_ARTIST_RELEASE;
    }

    /** Układ z numerem z przodu ("NN. Artysta - Tytuł") - stosowany, gdy artysta zmienia się w wydaniu */
    private static function hasLeadingTrackNumber(SplFileInfo $file): bool
    {
        $basename = $file->getBasename('.' . $file->getExtension());

        return preg_match('/^\d+\.\s/', $basename) === 1;
    }
}
