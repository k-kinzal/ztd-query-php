<?php

declare(strict_types=1);

namespace HarborOps\Command;

use HarborOps\Import\Importer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\{InputArgument,InputInterface};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'posts:import', description: 'Import JSON Lines from standard input as one atomic transaction')]
final class ImportPosts extends OperationCommand
{
    public function __construct(private readonly Importer $importer)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('source', InputArgument::REQUIRED, 'Source ID');
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $stream = $input instanceof \Symfony\Component\Console\Input\StreamableInputInterface ? $input->getStream() : null;
        $owned = $stream === null;
        $stream ??= fopen('php://stdin', 'rb');
        try {
            $result = $this->importer->import($this->positive($input, 'source'), $stream);
        } finally {
            if ($owned && is_resource($stream)) {
                fclose($stream);
            }
        }
        $output->writeln($result['inserted'].' inserted; '.$result['unchanged'].' unchanged.');
        return self::SUCCESS;
    }
}
