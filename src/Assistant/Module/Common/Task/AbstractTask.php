<?php

namespace Assistant\Module\Common\Task;

use Monolog\Logger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractTask extends Command
{
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
}
