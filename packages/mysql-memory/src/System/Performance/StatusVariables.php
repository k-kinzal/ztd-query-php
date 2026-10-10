<?php

declare(strict_types=1);

namespace MySqlMemory\System\Performance;

use MySqlMemory\Instance;
use MySqlMemory\Value\Zone;
use SqlSemantics\Contract\GrammarRelease;

/**
 * The status variables of a release, as resources/status holds them, with the values the emulated server shows.
 *
 * SQL request, command and protocol byte counters reflect execution in their scope. Unmodeled storage and
 * operating-system counters retain their catalog defaults. What describes the
 * configuration of the server reads as on a server of the release; Uptime and
 * Uptime_since_flush_status subtract the real start or flush time from the reading statement's
 * timestamp, with unsigned wraparound for a pinned timestamp before that origin. Threads_connected counts the
 * clients connected, Threads_running includes the reader and enabled event daemon, and
 * Connections counts client sessions opened so far. The connection maximum survives
 * disconnects until FLUSH STATUS and records its clock in the reading session time zone.
 * A release without a catalog of its own has that of the latest series of its major version.
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
    public function values(Instance $instance, bool $global, bool $threaded, int $connected, bool $tabled = true, ?int $connection = null, ?float $instant = null, ?Zone $zone = null): array
    {
        $instant ??= $instance->registry->threads->now();
        $connections = $this->connections($instance, $connected, $zone ?? Zone::utc());
        $rows = [];
        foreach ($this->entries as [$name, $scope, $value, $listed]) {
            if (($global && $scope === 'Session') || ($threaded && $scope === 'Global') || ($tabled && !$listed)) {
                continue;
            }
            $rows[] = [$name, match ($name) {
                'Uptime' => \MySqlMemory\Value\Integer::text((int) $instant - (int) $instance->started, true),
                'Uptime_since_flush_status' => \MySqlMemory\Value\Integer::text((int) $instant - (int) ($instance->registry->status->flushedAt ?? $instance->started), true),
                'Queries', 'Aborted_clients' => (string) $instance->registry->status->read($name),
                'Bytes_received', 'Bytes_sent' => (string) $instance->registry->status->read($name, $global ? null : $connection),
                default => $connections[$name] ?? ($name === 'Questions' || str_starts_with($name, 'Com_') ? (string) $instance->registry->status->read($this->counter($name), $global ? null : $connection) : $value),
            }];
        }

        return $rows;
    }

    /**
     * Reads connection totals and the maximum in the observing session's time zone.
     * An enabled event scheduler counts as running but is not a client connection.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/server-status-variables.html.
     *
     * @return array<string, string>
     */
    public function connections(Instance $instance, int $connected, Zone $zone): array
    {
        $threads = $instance->registry->threads;

        return [
            'Connections' => (string) $instance->connections(),
            'Threads_connected' => (string) $connected,
            'Threads_running' => $instance->registry->eventScheduler->row() === null ? '1' : '2',
            'Max_used_connections' => (string) $threads->maximum,
            'Max_used_connections_time' => gmdate('Y-m-d H:i:s', $zone->local((int) $threads->maximumAt)),
        ];
    }

    /**
     * Resolves legacy replication counter names to the same underlying event totals.
     * MySQL 8.0 exposes both replica and slave aliases, independently of the SQL spelling.
     */
    public function counter(string $name): string
    {
        return match ($name) {
            'Com_show_slave_hosts' => 'Com_show_replicas',
            'Com_show_slave_status' => 'Com_show_replica_status',
            'Com_show_master_status' => 'Com_show_binary_log_status',
            default => $name,
        };
    }
}
