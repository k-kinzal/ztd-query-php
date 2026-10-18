<?php

declare(strict_types=1);

namespace HarborOps\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\{InputArgument, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'source:add', description: 'Register an external content source')]
final class AddSource extends OperationCommand
{
    public function __construct(private readonly \HarborOps\Community\Communities $service)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('community', InputArgument::REQUIRED, 'Community ID');
        $this->addArgument('name', InputArgument::REQUIRED, 'Source name');
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $id = $this->service->source($this->positive($input, 'community'), (string)$input->getArgument('name'));
        $output->writeln('Source '.$id.' registered.');
        return self::SUCCESS;
    }
}
