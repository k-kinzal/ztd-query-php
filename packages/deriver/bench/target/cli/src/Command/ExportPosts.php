<?php

declare(strict_types=1);

namespace HarborOps\Command;

use HarborOps\Export\Exporter;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\{InputArgument,InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'posts:export', description: 'Stream community posts as JSON Lines or formula-safe CSV')]
final class ExportPosts extends OperationCommand
{
    public function __construct(private readonly Exporter $exporter)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addArgument('community', InputArgument::REQUIRED, 'Community ID')->addOption('state', null, InputOption::VALUE_REQUIRED, 'Publication state', 'published')->addOption('format', null, InputOption::VALUE_REQUIRED, 'jsonl or csv', 'jsonl');
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');
        if (!in_array($format, ['jsonl','csv'], true)) {
            throw new InvalidArgumentException('Format must be jsonl or csv.');
        }
        $stream = fopen('php://memory', 'w+');
        try {
            if ($format === 'csv') {
                $output->writeln('id,external_id,title,body,state,published_at');
            }
            foreach ($this->exporter->rows($this->positive($input, 'community'), (string)$input->getOption('state')) as $row) {
                if ($format === 'jsonl') {
                    $output->writeln(json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), OutputInterface::OUTPUT_RAW);
                    continue;
                }
                ftruncate($stream, 0);
                rewind($stream);
                fputcsv($stream, array_map(fn ($value) => $this->exporter->csvCell((string)$value), $row), ',', '"', '');
                rewind($stream);
                $output->write(stream_get_contents($stream), false, OutputInterface::OUTPUT_RAW);
            }
        } finally {
            fclose($stream);
        }
        return self::SUCCESS;
    }
}
