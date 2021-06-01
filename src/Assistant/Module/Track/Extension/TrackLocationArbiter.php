<?php

namespace Assistant\Module\Track\Extension;

use Assistant\Module\Common\Extension\Config;
use Assistant\Module\File\Model\IncomingTrack;
use Assistant\Module\Track\Model\Track;
use SplFileInfo;

final class TrackLocationArbiter
{
    private const LOCATION_IN_COLLECTION = 'in_collection';

    private const LOCATION_IN_INCOMING = 'in_incoming';

    private const LOCATION_UNSUPPORTED = null;

    public function __construct(private Config $config)
    {
    }

    public function isInCollection(mixed $file): bool
    {
        $location = $this->getLocation($file);
        $result = $location === self::LOCATION_IN_COLLECTION;

        return $result;
    }

    public function isInIncoming(mixed $file): bool
    {
        $location = $this->getLocation($file);
        $result = $location === self::LOCATION_IN_INCOMING;

        return $result;
    }

    private function getLocation(mixed $file): ?string
    {
        $pathname = $this->getPathname($file);

        // uwaga, kolejność warunków jest istotna, ponieważ _new zawiera się w /collection.
        // kolejność sprawdzania warunków jest sporym uproszczeniem, ale chwilowo powinno wystarczyć.
        // do ogarnięcia w wolnym czasie.

        if (str_starts_with($pathname, $this->config->get('collection.incoming_dir'))) {
            return self::LOCATION_IN_INCOMING;
        }
        if (str_starts_with($pathname, $this->config->get('collection.root_dir'))) {
            return self::LOCATION_IN_COLLECTION;
        }

        // może lepsze będzie rzucanie wyjątkiem nż zwracanie null-a?
        return self::LOCATION_UNSUPPORTED;
    }

    private function getPathname(mixed $pathname): ?string
    {
        $file = $pathname;

        if (is_string($pathname)) {
            $file = new SplFileInfo($pathname);
        } elseif ($pathname instanceof Track || $pathname instanceof IncomingTrack) {
            $file = $pathname->getFile();
        }

        assert($file instanceof SplFileInfo); // na szybko, może lepszy będzie instanceof i exception
        assert($file->isFile());

        return $file->getPathname();
    }
}
