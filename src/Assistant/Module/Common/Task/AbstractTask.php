<?php

namespace Assistant\Module\Common\Task;

use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractTask extends Command
{
    public const IGNORED_PARAMETERS = [ 'help', 'ansi', 'no-ansi', 'no-interaction', 'quiet', 'verbose', 'version' ];

    protected InputInterface $input;
    protected OutputInterface $output;

    public function __construct(protected Logger $logger)
    {
        parent::__construct();
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $this->logger->pushProcessor(function ($record) {
            $record['extra']['task'] = $this->getName();

            return $record;
        });
    }

    protected static function getInputParams(InputInterface $input): array
    {
        $params = array_merge($input->getArguments(), $input->getOptions());
        $withoutIgnoredParams = array_diff_key($params, array_flip(self::IGNORED_PARAMETERS));

        return $withoutIgnoredParams;
    }
}
