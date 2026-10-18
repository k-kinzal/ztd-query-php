<?php

declare(strict_types=1);

namespace HarborOps\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'privacy:erase', description: 'Execute an approved member erasure')]
final class ExecuteErasure extends OperationCommand
{
    public function __construct(private readonly \HarborOps\Privacy\Retention $service)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('request', InputArgument::REQUIRED, 'Request ID');
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $this->service->erase($this->positive($input, 'request'));
        $output->writeln('Member erased.');
        return self::SUCCESS;
    }
}
