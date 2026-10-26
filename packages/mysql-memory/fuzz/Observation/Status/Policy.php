<?php

declare(strict_types=1);

namespace Fuzz\Observation\Status;

use Fuzz\Observation\Traffic;

/**
 * Explicit comparison rules for isolated, complete SHOW STATUS observations.
 * Command counters retain SQL history. Named environmental quantities use independently validated value domains.
 * Unknown variables retain their exact value; a new catalog entry is not exempted
 * merely because two readings differ.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/server-status-variables.html.
 */
final class Policy
{
    /**
     * Classifies the documented quantity independently of its observed value.
     */
    public function kind(string $name, bool $global): string
    {
        if (str_starts_with($name, 'Com_') || $name === 'Questions') {
            return $global ? 'history' : 'exact';
        }
        if (in_array($name, ['Connections', 'Queries', 'Flush_commands', 'Aborted_clients', 'Deprecated_use_i_s_processlist_count'], true)) {
            return 'history';
        }
        if (in_array($name, ['Bytes_received', 'Bytes_sent'], true)) {
            return 'wire';
        }
        if (in_array($name, ['Uptime', 'Uptime_since_flush_status'], true)) {
            return 'clock';
        }
        if ($name === 'Deprecated_use_i_s_processlist_last_timestamp') {
            return 'event-time';
        }
        if ($name === 'Max_used_connections_time') {
            return 'connection-time';
        }
        return 'exact';
    }

    /**
     * Returns a validated quantity, or null to retain the entire raw observation.
     */
    public function value(string $name, string $value, bool $global, Snapshot $before, Traffic $traffic, float $elapsed): ?string
    {
        $kind = $this->kind($name, $global);
        if ($kind === 'exact') {
            return (new Environment())->value($name, $value);
        }
        $base = ($kind === 'wire' && !$global ? $before->session : $before->global)[$name] ?? null;
        if ($kind === 'connection-time') {
            return $value === $base && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\z/', $value) === 1 ? '<unchanged-connection-maximum-time>' : null;
        }
        if ($base === null || !ctype_digit($base) || !ctype_digit($value) || bccomp($base, '18446744073709551615') > 0 || bccomp($value, '18446744073709551615') > 0) {
            return null;
        }
        if ($kind === 'event-time') {
            return $this->eventTime($value, $base, $before);
        }
        $difference = bcsub($value, $base);
        if ($kind === 'clock') {
            $seconds = bcmod(bcadd($difference, '18446744073709551616'), '18446744073709551616');

            return bccomp($seconds, (string) (int) ceil($elapsed + 1)) <= 0 ? '<bounded-elapsed-time>' : null;
        }
        if ($kind === 'wire') {
            $bytes = $traffic->since($global ? $before->globalTraffic : $before->sessionTraffic, $name === 'Bytes_sent');

            return ctype_digit($bytes) && bccomp($difference, $bytes) === 0 ? '<measured-wire-bytes>' : null;
        }

        return bccomp($difference, '0') >= 0 ? $difference : null;
    }

    /**
     * A status listing cannot move a process-list event's clock. Zero means no read since startup.
     * Different instance histories may have different clocks, but must obey that relationship.
     */
    public function eventTime(string $value, string $base, Snapshot $before): ?string
    {
        $count = $before->global['Deprecated_use_i_s_processlist_count'] ?? null;
        if (!ctype_digit($value) || $value !== $base || $count === null || !ctype_digit($count) || bccomp($count, '18446744073709551615') > 0) {
            return null;
        }

        return (bccomp($count, '0') === 0) === (bccomp($value, '0') === 0) ? '<unchanged-server-event-time>' : null;
    }
}
