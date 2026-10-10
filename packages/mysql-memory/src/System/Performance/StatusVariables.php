<?php

declare(strict_types=1);

namespace MySqlMemory\System\Performance;

use MySqlMemory\Instance;
use SqlSemantics\Contract\GrammarRelease;

/**
 * The status variables of a release, as resources/status holds them, with the values the emulated server shows.
 *
 * SQL request and command counters reflect execution in their scope. Unmodeled storage and
 * operating-system counters retain their catalog defaults. What describes the
 * configuration of the server reads as on a server of the release; Uptime and
 * Uptime_since_flush_status count the seconds since the server started, Threads_connected the
 * sessions connected, Threads_running the one running the statement, and Connections the
 * sessions opened so far. A release without a catalog of its own has that of the latest
 * series of its major version.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/server-status-variables.html.
 *
 * @visibility MySqlMemory
 */
final class StatusVariables
{
    /**
     * @var array<string, self>
     */
    public static array $releases = [];

    /**
     * @param list<array{string, string, string, bool}> $entries The name, the scopes (Global, Session or Both) and the value of each variable, and whether the tables of the Performance Schema list it, in the order SHOW STATUS lists them
     */
    public function __construct(public readonly array $entries)
    {
    }

    /**
     * Answers the status variables of a release.
     */
    public static function of(GrammarRelease $release): self
    {
        if (!isset(self::$releases[$release->value])) {
            $directory = dirname(__DIR__, 3) . '/resources/status/';
            $files = glob($directory . substr($release->value, 0, 9) . '*.php');
            $major = glob($directory . substr($release->value, 0, 7) . '*.php');
            $file = is_array($files) && $files !== [] ? $files[0] : (is_array($major) && $major !== [] ? $major[count($major) - 1] : $directory . GrammarRelease::MySql847->value . '.php');
            /** @var list<array{string, string, string, bool}> $entries */
            $entries = require $file;
            self::$releases[$release->value] = new self($entries);
        }

        return self::$releases[$release->value];
    }

    /**
     * Answers the name and value of each variable of a scope: of the global scope, of a session, or with a session value only.
     *
     * @param bool $global Whether to answer the global values
     * @param bool $threaded Whether to answer only the variables that have a session value
     * @param int $connected The sessions connected
     * @param bool $tabled Whether to answer only the variables the tables of the Performance Schema list, which leave out the statement counters
     *
     * @return list<array{string, string}>
     */
    public function values(Instance $instance, bool $global, bool $threaded, int $connected, bool $tabled = true, ?int $connection = null): array
    {
        $uptime = (string) max(0, (int) floor(microtime(true) - $instance->started));
        $rows = [];
        foreach ($this->entries as [$name, $scope, $value, $listed]) {
            if (($global && $scope === 'Session') || ($threaded && $scope === 'Global') || ($tabled && !$listed)) {
                continue;
            }
            $rows[] = [$name, match ($name) {
                'Uptime' => $uptime,
                'Uptime_since_flush_status' => (string) max(0, (int) floor($instance->registry->threads->now() - ($instance->registry->status->flushedAt ?? $instance->started))),
                'Threads_connected', 'Max_used_connections' => (string) $connected,
                'Threads_running' => '1',
                'Connections' => (string) $instance->connections(),
                'Queries' => (string) $instance->registry->status->read($name),
                default => $name === 'Questions' || str_starts_with($name, 'Com_') ? (string) $instance->registry->status->read($name, $global ? null : $connection) : $value,
            }];
        }

        return $rows;
    }
}
