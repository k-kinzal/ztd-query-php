<?php

declare(strict_types=1);

namespace HarborOps\Command;

use HarborOps\Queue\Worker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\{InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(name: 'queue:work', description: 'Drain available jobs with leases, retries and graceful signal handling')]
final class WorkQueue extends OperationCommand implements SignalableCommandInterface
{
    private bool $stopping = false;
    public function __construct(private readonly Worker $worker, private readonly LockFactory $locks)
    {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum jobs per invocation', '100');
    }
    public function getSubscribedSignals(): array
    {
        return [SIGINT,SIGTERM];
    }
    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->stopping = true;
        return false;
    }
    protected function perform(InputInterface $input, OutputInterface $output): int
    {
        $lock = $this->locks->createLock('digest-worker', 300);
        if (!$lock->acquire()) {
            $output->writeln('Another digest worker holds the delivery lock.');
            return self::SUCCESS;
        }
        try {
            $result = $this->worker->run($this->positive($input, 'limit', true), function () use ($lock): bool {
                $lock->refresh();
                return $this->stopping;
            });
        } finally {
            $lock->release();
        }
        $output->writeln($result['completed'].' completed; '.$result['failed'].' deferred or dead.');
        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
