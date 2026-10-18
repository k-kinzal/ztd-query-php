<?php

declare(strict_types=1);

namespace HarborOps\Command;

use HarborOps\Database;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'database:migrate', description: 'Apply the operations database schema')]
final class Migrate extends OperationCommand
{
    public function __construct(private readonly Database $database)
    {
        parent::__construct();
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $this->database->migrate(dirname(__DIR__, 2).'/database/001_operations.sql');
        $output->writeln('Schema is current.');
        return self::SUCCESS;
    }
}
