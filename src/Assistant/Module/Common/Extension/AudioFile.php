<?php

namespace Assistant\Module\Common\Extension;

use getID3;
use SplFileInfo;

final class AudioFile extends SplFileInfo
{
    private ?array $audioInfo = null;

    public function getBitrate(): ?int
    {
        $this->ensureAnalyzed();

        return $this->audioInfo['bitrate'];
    }

    public function getSampleRate(): ?int
    {
        $this->ensureAnalyzed();

        return $this->audioInfo['sample_rate'];
    }

    public function getChannelMode(): ?string
    {
        $this->ensureAnalyzed();

        return $this->audioInfo['channel_mode'];
    }

    public function getBitrateMode(): ?string
    {
        $this->ensureAnalyzed();

        return $this->audioInfo['bitrate_mode'];
    }

    private function ensureAnalyzed(): void
    {
        if ($this->audioInfo !== null) {
            return;
        }

        // @fixme: Na ten moment wystarczy, ale nie można wstrzykiwać zależności w ten sposób.
        //         To oznacza też, że ten model (a więc i także cały model Track) i musi byc tworzony orzez coś wyższego

        $id3 = new getID3();
        $raw = $id3->analyze($this->getPathname());

        $this->audioInfo = [
            'bitrate' => isset($raw['bitrate']) ? (int) round($raw['bitrate'] / 1000) : null,
            'sample_rate' => $raw['audio']['sample_rate'] ?? null,
            'channel_mode' => $raw['audio']['channelmode'] ?? null,
            'bitrate_mode' => $raw['audio']['bitrate_mode'] ?? null,
        ];
    }
}
