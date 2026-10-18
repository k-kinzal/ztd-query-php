<?php

declare(strict_types=1);

namespace HarborOps\Command;

use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'appeal:decide', description: 'An independent operator decides a moderation appeal')]
final class DecideAppeal extends OperationCommand
{
    public function __construct(private readonly \HarborOps\Moderation\Reviews $service)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('appeal', InputArgument::REQUIRED, 'Appeal ID');
        $this->addArgument('decision', InputArgument::REQUIRED, 'restore or reject');
        $this->addArgument('reason', InputArgument::REQUIRED, 'Decision explanation');
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $decision = (string)$input->getArgument('decision');
        if (!in_array($decision, ['restore','reject'], true)) {
            throw new InvalidArgumentException('Decision must be restore or reject.');
        } $this->service->decideAppeal($this->positive($input, 'appeal'), $decision === 'restore', (string)$input->getArgument('reason'));
        $output->writeln('Appeal decided.');
        return self::SUCCESS;
    }
}
