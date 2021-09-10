<?php

namespace Assistant\Module\Collection\Extension;

use Countable;
use IteratorAggregate;
use SplFileInfo;
use Symfony\Component\Finder\Finder as Service;
use Symfony\Component\Finder\Iterator\FileTypeFilterIterator;

final class Finder implements IteratorAggregate, Countable
{
    public const MODE_DIRECTORIES_ONLY = FileTypeFilterIterator::ONLY_DIRECTORIES;

    public const MODE_FILES_ONLY = FileTypeFilterIterator::ONLY_FILES;

    private const SUPPORTED_MODES = [
        self::MODE_DIRECTORIES_ONLY,
        self::MODE_FILES_ONLY,
    ];

    private const DEFAULT_SKIP_SELF = false;

    private const SYNOLOGY_EXTENDED_ATTRIBUTES_DIR = '@eaDir';

    private Service $service;

    public function __construct(Service $service)
    {
        $this->service = $service;
    }

    /**
     * @todo Dopisać testy oraz uprościć poniższą logikę, jeśli się da
     *
     * @param array $params
     * @return Finder
     */
    public static function create(array $params): Finder
    {
        /** @todo Tablicę $params zamienić na value object */

        if (empty($params['pathname'])) {
            throw new \BadMethodCallException('Pathname cannot be empty');
        }

        $service = new Service();

        $skipSelf = $params['skip_self'] ?? self::DEFAULT_SKIP_SELF;

        if ($skipSelf) {
            $service->in($params['pathname']);
        } else {
            [ 'basename' => $basename, 'dirname' => $dirname ] = pathinfo($params['pathname']);

            $service
                ->in($dirname)
                ->path($basename);
        }

        if (isset($params['recursive']) && $params['recursive'] === false) {
            $depth = $skipSelf === true || is_file($params['pathname']) ? 0 : 1; // :/

            $service->depth($depth);
        }

        if (!empty($params['restrict'])) {
            /*
            $restrictedPaths = array_map(
                static fn($path) => ltrim($path, DIRECTORY_SEPARATOR),
                (array) $params['restrict']
            );

            $service->path($restrictedPaths);
            */

            // powyższy kod konfliktuje ze $skipSelf = true i nie odfiltrowuje niechcianych katalogów
            $restrictedPaths = (array) $params['restrict'];

            $service->filter(static function (SplFileInfo $node) use ($restrictedPaths): bool {
                $result = false;

                foreach ($restrictedPaths as $path) {
                    if (str_contains($node->getPathname(), $path)) {
                        $result = true;
                        break;
                    }
                }

                return $result;
            });
        }

        // może node_type zamiast mode?
        if (isset($params['mode'])) {
            switch ($params['mode']) {
                case self::MODE_DIRECTORIES_ONLY:
                    $service->directories();
                    break;

                case self::MODE_FILES_ONLY:
                    $service->files();
                    break;

                default:
                    throw new \BadMethodCallException(
                        sprintf('Invalid mode ("%s"). Supported modes are: %s)', $params['mode'], self::SUPPORTED_MODES)
                    );
            }
        }

        $filter = $params['filter'] ?? self::defaultFilter();

        $service
            ->exclude(self::SYNOLOGY_EXTENDED_ATTRIBUTES_DIR)
            ->filter($filter);

        return new self($service);
    }

    public function append(iterable $iterator): void
    {
        $this->service->append($iterator);
    }

    public function getIterator(): array|\Traversable|\Iterator|\AppendIterator
    {
        return $this->service->getIterator();
    }

    public function count(): int
    {
        return $this->service->count();
    }

    private static function defaultFilter(): \Closure
    {
        $defaultFilter = static fn(SplFileInfo $node): bool => (
            $node->isDir() || ($node->isFile() && $node->getExtension() === 'mp3')
        );

        return $defaultFilter;
    }
}
