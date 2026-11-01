<?php

declare(strict_types=1);

namespace MySqlMemory\Server;

use MySqlMemory\Instance;
use RuntimeException;
use Throwable;

/**
 * Starts the library listener in the process owned by MemoryDatabase.
 *
 * @visibility MySqlMemory
 */
final class Process
{
    /**
     * Receives the parent's private startup arguments and serves its selected release.
     *
     * @throws RuntimeException When the listener encounters an internal failure
     */
    public function serve(string $configuration): void
    {
        /** @var array{version: string, databases: list<string>, globals: array<string, string|int|null>, clientHost: string|null, supervised: bool, routineTimestamps: array<string, array{int, int}>, resourcePriorities: bool, resourceProcessors: positive-int} $options The arguments serialized by MemoryDatabase, never client input */
        $options = unserialize($configuration, ['allowed_classes' => false]);
        $tables = (new InitialTables())->read(STDIN);
        $timeZones = isset($tables['zones']) ? array_map(static fn (array $row): array => [$row[0], (int) $row[1], $row[2] === 'Y'], $tables['zones']) : null;
        unset($tables['zones']);
        $listener = new Listener(new Instance(...$options, help: $tables, timeZones: $timeZones), 'tcp://127.0.0.1:0');
        fwrite(STDOUT, 'ready ' . $listener->open() . "\n");
        fflush(STDOUT);
        try {
            $listener->serve();
        } catch (Throwable $failure) {
            // Report outside the SQL protocol before the failed process closes its clients.
            (new Failures(STDOUT))->report($failure);
            throw new RuntimeException('The mysql-memory process encountered an internal failure.', 0, $failure);
        }
    }
}
