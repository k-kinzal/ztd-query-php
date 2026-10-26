<?php

declare(strict_types=1);

namespace Fuzz\Observation;

use RuntimeException;

/**
 * Samples mysqlnd's independent transport counters at the SQL execution boundary.
 *
 * The observer runs synchronously without other client traffic. Preparation finishes
 * before the execution sample; result consumption finishes before completion. Cursor
 * closure, statement destruction and warning probes follow the completion sample.
 * Source: https://www.php.net/manual/en/mysqlnd.stats.php.
 *
 * @phpstan-type Counters array{sent: numeric-string, received: numeric-string, commands: numeric-string, prepares: numeric-string, instant: float}
 */
final class Traffic
{
    /**
     * @var Counters|null The client counters immediately before execution
     */
    public ?array $execution = null;

    /**
     * @var Counters|null The client counters after consuming the result
     */
    public ?array $completion = null;

    /**
     * Retains the pre-execution measurement even if execution subsequently fails.
     */
    public function ready(?Record $record): void
    {
        $this->execution = self::sample();
        if ($record !== null) {
            $record->measurements['wire'] = ['execution' => $this->execution, 'completion' => null];
        }
    }

    /**
     * Retains the completion measurement before any diagnostic SQL is issued.
     */
    public function complete(?Record $record): void
    {
        $this->completion = self::sample();
        if ($record !== null) {
            $record->measurements['wire'] = ['execution' => $this->execution, 'completion' => $this->completion];
        }
    }

    /**
     * @return Counters
     * @throws RuntimeException When required mysqlnd statistics are unavailable or malformed
     */
    public static function sample(): array
    {
        if (!function_exists('mysqli_get_client_stats') || !filter_var(ini_get('mysqlnd.collect_statistics'), FILTER_VALIDATE_BOOLEAN)) {
            throw new RuntimeException('Status comparison requires enabled mysqlnd client statistics.');
        }
        $statistics = mysqli_get_client_stats();
        $sent = $statistics['bytes_sent'] ?? null;
        $received = $statistics['bytes_received'] ?? null;
        $commands = $statistics['packets_sent_command'] ?? null;
        $prepares = $statistics['com_stmt_prepare'] ?? null;
        if (!is_string($sent) || !ctype_digit($sent) || !is_string($received) || !ctype_digit($received)
            || !is_string($commands) || !ctype_digit($commands) || !is_string($prepares) || !ctype_digit($prepares)) {
            throw new RuntimeException('Cannot read mysqlnd transport counters for status comparison.');
        }
        return ['sent' => $sent, 'received' => $received, 'commands' => $commands, 'prepares' => $prepares, 'instant' => microtime(true)];
    }

    /**
     * Measures the transport counted by a status row since its independent baseline.
     * Server sent bytes exclude the current reply; received bytes include its request.
     *
     * @return numeric-string
     * @throws RuntimeException When either execution boundary was not sampled or counters decreased
     */
    public function since(self $before, bool $serverSent): string
    {
        $first = $serverSent ? $before->execution : $before->completion;
        $last = $serverSent ? $this->execution : $this->completion;
        $counter = $serverSent ? 'received' : 'sent';
        if ($first === null || $last === null || bccomp($last[$counter], $first[$counter]) < 0) {
            throw new RuntimeException('Status comparison requires complete, monotonic transport measurements.');
        }
        $bytes = bcsub($last[$counter], $first[$counter]);
        if ($serverSent) {
            return $bytes;
        }
        /**
         * mysqlnd_pfc::send starts packets_sent at one before its packet loop and
         * increments it once per packet. bytes_sent therefore counts one extra
         * four-byte header per send, including commands split over several packets.
         * php_mysqlnd_cmd_write counts exactly one command for each such send.
         * The packet statistics lookup also lacks the prepare-response entries:
         * both default to STAT_BYTES_SENT, adding the 16-byte successful prepare
         * response and one packet to that counter. This contract measures successful
         * SHOW STATUS preparation; SQL errors never reach this normalization.
         * Preserve the raw counters and remove these accounting contributions only.
         * Sources: php/php-src, ext/mysqlnd/mysqlnd_protocol_frame_codec.c (send),
         * ext/mysqlnd/mysqlnd_wireprotocol.c (packet statistic maps and cmd_write),
         * and ext/mysqlnd/mysqlnd_enum_n_def.h (packet kinds), PHP 8.1 through 8.5.
         */
        if (bccomp($last['commands'], $first['commands']) < 0 || bccomp($last['prepares'], $first['prepares']) < 0) {
            throw new RuntimeException('Client command counters decreased during status sampling.');
        }
        $bytes = bcsub($bytes, bcmul(bcsub($last['commands'], $first['commands']), '4'));
        $bytes = bcsub($bytes, bcmul(bcsub($last['prepares'], $first['prepares']), '17'));
        if (bccomp($bytes, '0') < 0) {
            throw new RuntimeException('Client command accounting exceeds measured transport bytes.');
        }
        return $bytes;
    }
}
