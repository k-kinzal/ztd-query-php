<?php

declare(strict_types=1);

namespace HarborOps\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'privacy:approve', description: 'Approve a request made by a different operator')]
final class ApproveErasure extends OperationCommand
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
        $this->service->approve($this->positive($input, 'request'));
        $output->writeln('Erasure approved.');
        return self::SUCCESS;
    }
}
