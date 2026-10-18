<?php

declare(strict_types=1);

namespace HarborOps\Command;

use HarborOps\Privacy\Retention;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\{InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'retention:purge', description: 'Remove expired content while preserving open moderation cases and appeals')]
final class PurgeRetention extends OperationCommand
{
    public function __construct(private readonly Retention $retention)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count eligible content without changing it');
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln($this->retention->purge((bool)$input->getOption('dry-run')).($input->getOption('dry-run') ? ' eligible posts.' : ' expired posts removed.'));
        return self::SUCCESS;
    }
}
