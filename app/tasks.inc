<?php

use Assistant\Module\Collection\Task\CleanerTask;
use Assistant\Module\Collection\Task\IndexerTask;
use Assistant\Module\Track\Task\AudioDataCalculatorTask;
use Psr\Container\ContainerInterface as Container;

return static fn(Container $container): array => [
    AudioDataCalculatorTask::factory($container),
    CleanerTask::factory($container),
    IndexerTask::factory($container),
];
