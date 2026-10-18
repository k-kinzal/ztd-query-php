<?php

declare(strict_types=1);

namespace HarborOps\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'appeal:open', description: 'Record an appeal against an upheld case')]
final class OpenAppeal extends OperationCommand
{
    public function __construct(private readonly \HarborOps\Moderation\Reviews $service)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('case', InputArgument::REQUIRED, 'Case ID');
        $this->addArgument('reason', InputArgument::REQUIRED, 'Appeal explanation');
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Appeal '.$this->service->appeal($this->positive($input, 'case'), (string)$input->getArgument('reason')).' opened.');
        return self::SUCCESS;
    }
}
