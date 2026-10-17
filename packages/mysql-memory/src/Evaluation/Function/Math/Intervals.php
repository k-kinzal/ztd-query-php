<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Math;

use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * INTERVAL(N, N1, N2, ...): how many of the bounds N reaches, or -1 when N is NULL.
 *
 * The bounds are meant to be in ascending order. Eight or more bounds known when the statement is
 * resolved, none of them NULL, are read once and searched by halves (Ranges), as exact decimals
 * when N is exact and no bound is a double, else as doubles; a string bound is read as a double.
 * Otherwise the bounds are read for each row, in order, up to the first that N is below: a NULL
 * bound is passed, and N compares with a bound as an exact decimal when both are exact, else as
 * a double. An exact value is an integer, a decimal or a temporal value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/comparison-operators.html#function_interval;
 * the search is verified on a live 8.4 server.
 *
 * @visibility MySqlMemory
 */
final class Intervals
{
    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [new Routine('INTERVAL', 2, -1, $this->interval(...), 1, $this->resolve(...), true)];
    }

    /**
     * Reads eight or more bounds known for the statement once, when none is NULL.
     *
     * @param list<Evaluable> $arguments
     * @param list<bool> $known
     * @return list<Evaluable>
     */
    public function resolve(Frame $frame, array $arguments, array $known): array
    {
        $bounds = array_slice($arguments, 1);
        if (count($bounds) < 8 || in_array(false, array_slice($known, 1), true)) {
            return $arguments;
        }
        $exact = $this->exact($arguments[0]->domain()) && array_filter($bounds, static fn (Evaluable $bound): bool => $bound->domain()->kind === Kind::Double) === [];
        $values = [];
        foreach ($bounds as $bound) {
            $value = $bound->evaluate($frame);
            if ($value === null) {
                return $arguments;
            }
            $domain = $bound->domain();
            $real = $this->exact($domain) ? null : (float) Convert::toDouble($value, $domain, $frame->context);
            $values[] = $exact ? ($real === null ? (string) Convert::toDecimal($value, $domain, $frame->context) : Decimal::fromDouble($real)) : ($real ?? (float) Convert::toDouble($value, $domain, $frame->context));
        }

        return [$arguments[0], new Ranges($values, $exact, $bounds[0]->domain())];
    }

    /**
     * Tells whether a value compares exactly: an integer, a decimal or a temporal value.
     */
    public function exact(Domain $domain): bool
    {
        return in_array($domain->kind, [Kind::Integer, Kind::Decimal, Kind::Year, Kind::Bit, Kind::Date, Kind::Time, Kind::DateTime], true);
    }

    /**
     * INTERVAL(N, N1, N2, ...).
     *
     * @param list<Evaluable> $arguments
     */
    public function interval(Frame $frame, array $arguments): int
    {
        $number = $arguments[0];
        $value = $number->evaluate($frame);
        if ($value === null) {
            return -1;
        }
        $domain = $number->domain();
        $context = $frame->context;
        $exact = $this->exact($domain) ? (string) Convert::toDecimal($value, $domain, $context) : null;
        $real = $exact === null ? (float) Convert::toDouble($value, $domain, $context) : (float) $exact;
        $ranges = $arguments[1] ?? null;
        if ($ranges instanceof Ranges) {
            return $ranges->search($ranges->exact ? (string) $exact : $real);
        }
        $passed = 0;
        foreach (array_slice($arguments, 1) as $bound) {
            $bounding = $bound->evaluate($frame);
            if ($bounding !== null) {
                $boundDomain = $bound->domain();
                $below = $exact !== null && $this->exact($boundDomain)
                    ? Decimal::compare($exact, (string) Convert::toDecimal($bounding, $boundDomain, $context)) < 0
                    : $real < (float) Convert::toDouble($bounding, $boundDomain, $context);
                if ($below) {
                    return $passed;
                }
            }
            $passed++;
        }

        return $passed;
    }
}
