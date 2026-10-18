<?php

declare(strict_types=1);

namespace HarborOps\Command;

use HarborOps\Database;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\{InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'case:list', description: 'List the oldest outstanding moderation cases with severity and ownership')]
final class ListCases extends OperationCommand
{
    public function __construct(private readonly Database $database)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum cases', '100');
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $limit = min(1000, $this->positive($input, 'limit', true));
        $rows = $this->database->rows("SELECT c.id,r.severity,p.title,c.assignee,c.created_at FROM cases c JOIN rules r ON r.id=c.rule_id JOIN posts p ON p.id=c.post_id WHERE c.state='open' ORDER BY CASE r.severity WHEN 'urgent' THEN 0 ELSE 1 END,c.created_at,c.id LIMIT ".$limit);
        (new SymfonyStyle($input, $output))->table(['Case','Severity','Title','Assigned operator','Created at'], $rows);
        return self::SUCCESS;
    }
}
