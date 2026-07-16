<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Common\Extension\ConsoleCommandRunner;
use Assistant\Module\Common\Extension\GetId3\Adapter as Id3Adapter;
use SplFileInfo;

/**
 * Wspólna dla obu kontrolerów edycji logika zapisu metadanych utworu (F11): zapis tagów ID3 oraz
 * opcjonalne obliczenie BPM/tonacji. Dzięki temu Track i IncomingTrack nie duplikują tego kodu.
 */
final readonly class TrackMetadataWriter
{
    /** Nazwa komendy konsolowej liczącej BPM/tonację (zob. AudioDataCalculatorTask) */
    private const CALCULATE_AUDIO_DATA_COMMAND = 'track:calculate-audio-data';

    public function __construct(
        private Id3Adapter $id3Adapter,
        private ConsoleCommandRunner $consoleCommandRunner,
    ) {
    }

    /**
     * Zapisuje metadane (tagi ID3) w pliku i zwraca listę niekrytycznych ostrzeżeń zapisu.
     *
     * @return string[]
     */
    public function write(SplFileInfo $file, array $metadata): array
    {
        $this->id3Adapter->setFile($file);
        $this->id3Adapter->writeMetadata($metadata);

        return $this->id3Adapter->getWriterWarnings();
    }

    /**
     * Zleca obliczenie i zapis BPM oraz tonacji dla podanego pliku - asynchronicznie (fire-and-forget),
     * bo analiza audio bywa czasochłonna i nie ma potrzeby czekać na jej zakończenie w żądaniu HTTP.
     */
    public function calculateAudioData(string $pathname): void
    {
        $this->consoleCommandRunner->runAsync([
            self::CALCULATE_AUDIO_DATA_COMMAND,
            '-w',
            $pathname,
        ]);
    }
}
