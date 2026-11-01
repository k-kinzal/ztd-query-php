<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary\Cache;

use MySqlMemory\Dictionary\Trigger;
use WeakMap;

/**
 * Connection-owned trigger definitions loaded with a persistent table handle.
 * A changed definition list or a table flush requires another load.
 *
 * @visibility MySqlMemory
 */
final class TriggerLoads
{
    /**
     * @var WeakMap<object, array<string, array<string, list<Trigger>>>>
     */
    private WeakMap $connections;

    /**
     * Starts with no loaded definitions.
     */
    public function __construct()
    {
        $this->connections = new WeakMap();
    }

    /**
     * Retains the current definitions and answers whether this handle was cold.
     *
     * @param list<Trigger> $triggers The definitions in firing order
     */
    public function load(object $connection, string $schema, string $table, array $triggers): bool
    {
        $loaded = $this->connections[$connection] ?? [];
        if (($loaded[$schema][$table] ?? null) === $triggers) {
            return false;
        }
        $loaded[$schema][$table] = $triggers;
        $this->connections[$connection] = $loaded;
        return true;
    }

    /**
     * Discards handles for one table, one schema, or the whole instance.
     */
    public function close(?string $schema = null, ?string $table = null): void
    {
        foreach ($this->connections as $connection => $loaded) {
            foreach ($loaded as $database => $tables) {
                foreach ($tables as $name => $definitions) {
                    if (($schema === null || $schema === $database) && ($table === null || $table === $name)) {
                        unset($loaded[$database][$name]);
                    }
                }
            }
            $this->connections[$connection] = $loaded;
        }
    }
}
