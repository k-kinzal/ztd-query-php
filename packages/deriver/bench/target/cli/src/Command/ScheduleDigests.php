<?php

declare(strict_types=1);

namespace HarborOps\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'digest:schedule', description: 'Schedule opt-in member digests for a completed UTC date')]
final class ScheduleDigests extends OperationCommand
{
    public function __construct(private readonly \HarborOps\Notification\Digests $service)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('date', InputArgument::REQUIRED, 'UTC date in YYYY-MM-DD format');
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->service->schedule((string)$input->getArgument('date')).' digests queued.');
        return self::SUCCESS;
    }
}
