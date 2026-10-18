<?php

declare(strict_types=1);

namespace HarborOps\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\{InputArgument, InputInterface, InputOption};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'community:create', description: 'Create a community and its retention policy')]
final class CreateCommunity extends OperationCommand
{
    public function __construct(private readonly \HarborOps\Community\Communities $service)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('slug', InputArgument::REQUIRED, 'Unique community slug');
        $this->addArgument('name', InputArgument::REQUIRED, 'Community name');
        $this->addOption('retention', null, InputOption::VALUE_REQUIRED, 'Days to retain posts', '365');
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $id = $this->service->create((string)$input->getArgument('slug'), (string)$input->getArgument('name'), $this->positive($input, 'retention', true));
        $output->writeln('Community '.$id.' created.');
        return self::SUCCESS;
    }
}
