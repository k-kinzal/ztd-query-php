<?php

declare(strict_types=1);

namespace HarborOps\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'case:claim', description: 'Claim an unassigned moderation case')]
final class AssignCase extends OperationCommand
{
    public function __construct(private readonly \HarborOps\Moderation\Reviews $service)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('case', InputArgument::REQUIRED, 'Case ID');
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $this->service->assign($this->positive($input, 'case'));
        $output->writeln('Case assigned.');
        return self::SUCCESS;
    }
}
