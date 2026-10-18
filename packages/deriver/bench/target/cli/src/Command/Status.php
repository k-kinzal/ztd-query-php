<?php

declare(strict_types=1);

namespace HarborOps\Command;

use HarborOps\Database;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'operations:status', description: 'Show publication, moderation, delivery and erasure backlog')]
final class Status extends OperationCommand
{
    public function __construct(private readonly Database $database)
    {
        parent::__construct();
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $rows = [];
        foreach (['posts','cases','appeals','jobs','erasure_requests'] as $table) {
            foreach ($this->database->rows('SELECT state,COUNT(*) AS total FROM '.$table.' GROUP BY state') as $row) {
                $rows[] = [$table,$row['state'],$row['total']];
            }
        }
        (new SymfonyStyle($input, $output))->table(['Resource','State','Count'], $rows);
        return self::SUCCESS;
    }
}
