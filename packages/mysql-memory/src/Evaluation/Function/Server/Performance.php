<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Server;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Value\Decimal;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The performance schema functions FORMAT_BYTES, FORMAT_PICO_TIME, PS_CURRENT_THREAD_ID and PS_THREAD_ID.
 *
 * FORMAT_BYTES() and FORMAT_PICO_TIME() read their argument as a DOUBLE. A count below the first
 * unit is written as an integer, truncated, right-aligned in 4 (bytes) or 3 (picoseconds)
 * characters; a larger one is divided down to the largest unit it reaches, from KiB to EiB or
 * from ns, us, ms, s, min and h to d, and written with two decimals, or in exponent notation from
 * 100000 of the largest unit on. The thread id of a session is its connection id plus the number
 * of threads a server of the release starts before its first client, as a freshly started server
 * numbers them; a connection id no session has answers NULL (verified on live 8.0.44, 8.4.7 and
 * 9.1.0 servers, whose thread ids also count the threads they started later).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Performance
{
    /**
     * The units of FORMAT_BYTES(), each with the number of bytes it holds.
     */
    public const BYTES = ['KiB' => 1024.0, 'MiB' => 1048576.0, 'GiB' => 1073741824.0, 'TiB' => 1099511627776.0, 'PiB' => 1125899906842624.0, 'EiB' => 1152921504606846976.0];

    /**
     * The units of FORMAT_PICO_TIME(), each with the number of picoseconds it holds.
     */
    public const TIMES = ['ns' => 1e3, 'us' => 1e6, 'ms' => 1e9, 's' => 1e12, 'min' => 6e13, 'h' => 3.6e15, 'd' => 8.64e16];

    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('FORMAT_BYTES', 1, 1, fn (Frame $f, array $a): ?string => $this->format($f, $a[0], self::BYTES, '%4d bytes')),
            new Routine('FORMAT_PICO_TIME', 1, 1, fn (Frame $f, array $a): ?string => $this->format($f, $a[0], self::TIMES, '%3d ps')),
            new Routine('PS_CURRENT_THREAD_ID', 0, 0, fn (Frame $f): int => $f->context->variables->connection + $this->offset($f->context->modes->release)),
            new Routine('PS_THREAD_ID', 1, 1, $this->thread(...)),
        ];
    }

    /**
     * Writes a quantity in the largest unit it reaches.
     *
     * @param array<string, float> $units The units, smallest first, with the quantity each holds
     * @param string $small The format of a quantity below the first unit
     */
    public function format(Frame $frame, Evaluable $argument, array $units, string $small): ?string
    {
        $value = Convert::toDouble($argument->evaluate($frame), $argument->domain(), $frame->context);
        if ($value === null) {
            return null;
        }
        $chosen = null;
        foreach ($units as $unit => $size) {
            if (abs($value) >= $size) {
                $chosen = $unit;
            }
        }
        if ($chosen === null) {
            return sprintf($small, (int) $value);
        }
        $scaled = $value / $units[$chosen];
        $text = abs($scaled) >= 100000.0 ? (string) preg_replace('/e([+-])(\d)$/', 'e${1}0$2', sprintf('%.2e', $scaled)) : sprintf('%.2f', $scaled);

        return $text . ' ' . $chosen;
    }

    /**
     * PS_THREAD_ID(connection): the thread id of the session of a connection id, or NULL when no session has it.
     *
     * A DECIMAL beyond the range of a BIGINT warns that it is an incorrect DECIMAL value (verified on a live 8.4 server).
     *
     * @param list<Evaluable> $arguments
     */
    public function thread(Frame $frame, array $arguments): ?int
    {
        $value = $arguments[0]->evaluate($frame);
        $domain = $arguments[0]->domain();
        if ($domain->kind === Kind::Decimal && $value !== null && (Decimal::compare(Decimal::round((string) $value, 0), (string) PHP_INT_MAX) > 0 || Decimal::compare(Decimal::round((string) $value, 0), (string) PHP_INT_MIN) < 0)) {
            $frame->context->warning(DataError::TruncatedWrongValue, 'DECIMAL', (string) $value);

            return null;
        }
        $connection = Convert::toInteger($value, $domain, $frame->context);
        if ($connection === null || !isset($frame->context->variables->instance->registry->threads->connected[$connection])) {
            return null;
        }

        return $connection + $this->offset($frame->context->modes->release);
    }

    /**
     * Answers the difference between the thread id and the connection id of a session: the threads a server of a release starts before its first client.
     */
    public function offset(GrammarRelease $release): int
    {
        return match ($release) {
            GrammarRelease::MySql8044 => 40,
            GrammarRelease::MySql901, GrammarRelease::MySql910 => 36,
            GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830, GrammarRelease::MySql847,
            GrammarRelease::PostgreSql166, GrammarRelease::PostgreSql172, GrammarRelease::Sqlite3472 => 37,
        };
    }
}
