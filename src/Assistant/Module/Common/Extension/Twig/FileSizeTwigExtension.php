<?php

namespace Assistant\Module\Common\Extension\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class FileSizeTwigExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_filesize', [$this, 'formatFileSize']),
        ];
    }

    public function formatFileSize(int|float|null $bytes): string
    {
        if ($bytes === null || $bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = (int) floor(log($bytes, 1024));
        $factor = min($factor, count($units) - 1);

        return sprintf('%.1f %s', $bytes / (1024 ** $factor), $units[$factor]);
    }
}
