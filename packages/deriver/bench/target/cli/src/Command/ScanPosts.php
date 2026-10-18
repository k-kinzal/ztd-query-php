<?php

declare(strict_types=1);

namespace HarborOps\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\{InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'moderation:scan', description: 'Apply active community rules to unscanned posts')]
final class ScanPosts extends OperationCommand
{
    public function __construct(private readonly \HarborOps\Moderation\Reviews $service)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum posts per transaction', '1000');
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->service->scan($this->positive($input, 'limit', true)).' posts scanned.');
        return self::SUCCESS;
    }
}
