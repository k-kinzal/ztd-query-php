<?php

declare(strict_types=1);

namespace HarborOps\Queue;

use DomainException;
use HarborOps\Notification\Digests;
use InvalidArgumentException;
use Throwable;

final class Worker
{
    public function __construct(private readonly Queue $queue, private readonly Digests $digests)
    {
    }
    public function run(int $limit, ?callable $shouldStop = null): array
    {
        if ($limit < 1 || $limit > 10000) {
            throw new InvalidArgumentException('Worker limit must be between 1 and 10000.');
        }
        $result = ['completed' => 0,'failed' => 0];
        for ($processed = 0;$processed < $limit;++$processed) {
            if ($shouldStop !== null && $shouldStop()) {
                break;
            }
            $job = $this->queue->claim();
            if ($job === null) {
                break;
            }
            try {
                match ($job['kind']) {
                    'digest' => $this->digests->send($job['payload']), default => throw new DomainException('No handler is registered for '.$job['kind'])
                };
                $this->queue->complete($job);
                ++$result['completed'];
            } catch (Throwable $error) {
                $this->queue->fail($job, $error);
                ++$result['failed'];
            }
        }
        return $result;
    }
}
