<?php

declare(strict_types=1);

namespace MySqlMemory\Server;

use RuntimeException;
use Throwable;

/**
 * Reports implementation failures through the process pipe, independently of SQL replies.
 *
 * @visibility MySqlMemory
 */
final class Failures
{
    private ?string $failure = null;

    /**
     * @param resource $channel The child output pipe, after its readiness line
     */
    public function __construct(private $channel)
    {
    }

    /**
     * Writes one bounded report before the failed child closes its client connections.
     */
    public function report(Throwable $failure): void
    {
        $message = $failure::class . ': ' . str_replace(["\r", "\n"], ' ', substr($failure->getMessage(), 0, 1024));
        fwrite($this->channel, 'failure ' . $message . "\n");
        fflush($this->channel);
    }

    /**
     * A reported failure remains a failure on every later check of this process.
     *
     * @throws RuntimeException When the child has reported an implementation failure
     */
    public function assertNone(): void
    {
        if ($this->failure === null) {
            $message = stream_get_contents($this->channel);
            if ($message === false) {
                $this->failure = 'Cannot read the mysql-memory process failure channel.';
            } elseif ($message !== '') {
                $this->failure = 'mysql-memory internal failure: ' . trim($message);
            }
        }
        if ($this->failure !== null) {
            throw new RuntimeException($this->failure);
        }
    }
}
