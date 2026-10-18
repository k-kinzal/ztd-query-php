<?php

declare(strict_types=1);

namespace HarborOps\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'privacy:request', description: 'Request member erasure subject to second operator approval')]
final class RequestErasure extends OperationCommand
{
    public function __construct(private readonly \HarborOps\Privacy\Retention $service)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('member', InputArgument::REQUIRED, 'Member ID');
        $this->addArgument('reason', InputArgument::REQUIRED, 'Request explanation');
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Request '.$this->service->request($this->positive($input, 'member'), (string)$input->getArgument('reason')).' created.');
        return self::SUCCESS;
    }
}
