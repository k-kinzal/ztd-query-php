<?php

declare(strict_types=1);

namespace HarborOps\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'digest:unsubscribe', description: 'Stop sending member digests')]
final class UnsubscribeDigest extends OperationCommand
{
    public function __construct(private readonly \HarborOps\Community\Communities $service)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('member', InputArgument::REQUIRED, 'Member ID');
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $this->service->subscribe($this->positive($input, 'member'), false);
        $output->writeln('Digest subscription disabled.');
        return self::SUCCESS;
    }
}
