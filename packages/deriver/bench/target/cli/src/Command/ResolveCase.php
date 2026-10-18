<?php

declare(strict_types=1);

namespace HarborOps\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'case:resolve', description: 'Resolve a claimed case with an attributable decision')]
final class ResolveCase extends OperationCommand
{
    public function __construct(private readonly \HarborOps\Moderation\Reviews $service)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('case', InputArgument::REQUIRED, 'Case ID');
        $this->addArgument('decision', InputArgument::REQUIRED, 'uphold or dismiss');
        $this->addArgument('reason', InputArgument::REQUIRED, 'Decision explanation');
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $this->service->resolve($this->positive($input, 'case'), (string)$input->getArgument('decision'), (string)$input->getArgument('reason'));
        $output->writeln('Case resolved.');
        return self::SUCCESS;
    }
}
