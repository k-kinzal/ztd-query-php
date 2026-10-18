<?php

declare(strict_types=1);

namespace HarborOps\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'queue:retry', description: 'Requeue a dead job after correcting its cause')]
final class RetryJob extends OperationCommand
{
    public function __construct(private readonly \HarborOps\Queue\Queue $service)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('job', InputArgument::REQUIRED, 'Dead job ID');
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $this->service->retry($this->positive($input, 'job'));
        $output->writeln('Job requeued.');
        return self::SUCCESS;
    }
}
