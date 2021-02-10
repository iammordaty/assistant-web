<?php

/** @noinspection PhpIncludeInspection */

$modulesIterator = new \FilesystemIterator(BASE_DIR . '/src/Assistant/Module/', \FilesystemIterator::SKIP_DOTS);

foreach ($modulesIterator as $moduleNode) {
    $routesPath = sprintf('%s/Resources/routes/', $moduleNode->getPathname());
    $hasRoutes = is_dir($routesPath);

    if (!$hasRoutes) {
        continue;
    }

    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($routesPath, \RecursiveDirectoryIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::SELF_FIRST,
        \RecursiveIteratorIterator::CATCH_GET_CHILD
    );

    foreach ($iterator as $node) {
        if ($node->isFile() && $node->getExtension() === 'inc') {
            require_once $node->getPathname();
        }

        unset($node);
    }

    unset($routesPath, $hasRoutes, $iterator);
}

unset($modulesIterator);
