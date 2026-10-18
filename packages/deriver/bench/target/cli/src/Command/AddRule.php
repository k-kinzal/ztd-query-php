<?php

declare(strict_types=1);

namespace HarborOps\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'rule:add', description: 'Add a literal content rule and rescan existing content')]
final class AddRule extends OperationCommand
{
    public function __construct(private readonly \HarborOps\Moderation\Reviews $service)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('community', InputArgument::REQUIRED, 'Community ID');
        $this->addArgument('name', InputArgument::REQUIRED, 'Rule name');
        $this->addArgument('phrase', InputArgument::REQUIRED, 'Case insensitive phrase');
        $this->addOption('severity', null, InputOption::VALUE_REQUIRED, 'review or urgent', 'review');
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $id = $this->service->addRule($this->positive($input, 'community'), (string)$input->getArgument('name'), (string)$input->getArgument('phrase'), (string)$input->getOption('severity'));
        $output->writeln('Rule '.$id.' created.');
        return self::SUCCESS;
    }
}
