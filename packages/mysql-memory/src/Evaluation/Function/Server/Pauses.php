<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Server;

use Closure;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Constancy;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Call;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Evaluation\Leaf\Assignment;
use MySqlMemory\Evaluation\Subquery\Existence;
use MySqlMemory\Evaluation\Subquery\Quantified;
use MySqlMemory\Evaluation\Subquery\ScalarRead;
use SqlSemantics\Contract\GrammarRelease;
use UnitEnum;

/**
 * The functions that take time: SLEEP and BENCHMARK.
 *
 * SLEEP(seconds) answers 0. Its argument is read as a DOUBLE; a NULL or negative duration is
 * ER_WRONG_ARGUMENTS (MySQL 5.6 sleeps no time and answers 0). BENCHMARK(count, expression)
 * evaluates the expression count times and answers 0; the count is read as an integer, a NULL
 * count answers NULL, and a negative count warns ER_WRONG_VALUE_FOR_TYPE and answers NULL without
 * evaluating the expression. The emulator does not stall the caller: a sleep passes on the clock
 * of the server, which SYSDATE() and the statements that follow read. An expression without
 * effects, which assigns no variable, reads no subquery and calls no function such as RAND() or
 * GET_LOCK() whose every call can differ, is evaluated only as often as the warnings it raises
 * still fit the diagnostics area, since every further evaluation does the same (verified on live
 * 5.6.51, 5.7.44 and 8.4.7 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/miscellaneous-functions.html#function_sleep,
 * https://dev.mysql.com/doc/refman/8.4/en/information-functions.html#function_benchmark.
 *
 * @visibility MySqlMemory
 */
final class Pauses
{
    /**
     * Answers the functions of the family.
     *
     * @return list<Routine>
     */
    public function routines(): array
    {
        return [
            new Routine('SLEEP', 1, 1, $this->sleep(...)),
            new Routine('BENCHMARK', 2, 2, $this->benchmark(...)),
        ];
    }

    /**
     * SLEEP(seconds): lets the duration pass on the clock of the server and answers 0.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws \MySqlMemory\Error\SqlError When the duration is NULL or negative
     */
    public function sleep(Frame $frame, array $arguments): int
    {
        $seconds = Convert::toDouble($arguments[0]->evaluate($frame), $arguments[0]->domain(), $frame->context);
        if ($seconds === null || $seconds < 0.0) {
            if ($frame->context->modes->release === GrammarRelease::MySql5651) {
                return 0;
            }
            throw StatementError::WrongArguments->error('sleep.');
        }
        $frame->context->variables->instance->registry->threads->pass($seconds);

        return 0;
    }

    /**
     * BENCHMARK(count, expression): evaluates the expression count times and answers 0.
     *
     * @param list<Evaluable> $arguments
     *
     * @throws \MySqlMemory\Error\SqlError When the expression fails, or a warning is an error
     */
    public function benchmark(Frame $frame, array $arguments): ?int
    {
        $count = Convert::toInteger($arguments[0]->evaluate($frame), $arguments[0]->domain(), $frame->context);
        if ($count === null) {
            return null;
        }
        if ($count < 0) {
            $frame->context->warning(DataError::WrongValueForType, 'count', (string) $count, 'benchmark');

            return null;
        }
        $expression = $arguments[1];
        $diagnostics = $frame->context->diagnostics;
        $seen = [];
        $pure = $this->pure($expression, $seen);
        for ($done = 0; $done < $count; $done++) {
            $before = $diagnostics->count();
            $expression->evaluate($frame);
            if ($pure && $diagnostics->count() === $before) {
                break;
            }
        }

        return 0;
    }

    /**
     * Tells whether evaluating an expression again does the same as the first time: it assigns no variable, reads no subquery and calls no function whose every call can differ.
     *
     * @param array<int, true> $seen The ids of the objects already walked
     */
    public function pure(object $node, array &$seen): bool
    {
        if ($node instanceof Closure || isset($seen[spl_object_id($node)])) {
            return true;
        }
        $seen[spl_object_id($node)] = true;
        if ($node instanceof Assignment || $node instanceof ScalarRead || $node instanceof Existence || $node instanceof Quantified) {
            return false;
        }
        if ($node instanceof Call) {
            if (in_array($node->routine->name, [...Constancy::ROW_FUNCTIONS, 'LAST_INSERT_ID'], true)) {
                return false;
            }
            $children = $node->arguments;
        } else {
            $children = [];
            $properties = get_object_vars($node);
            array_walk_recursive($properties, static function ($value) use (&$children): void {
                if (is_object($value) && !$value instanceof UnitEnum) {
                    $children[] = $value;
                }
            });
        }
        foreach ($children as $child) {
            if (!$this->pure($child, $seen)) {
                return false;
            }
        }

        return true;
    }
}
