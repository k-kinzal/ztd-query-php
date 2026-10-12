<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Window;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Operator\Coerce;
use MySqlMemory\Evaluation\Operator\Moments;
use MySqlMemory\Plan\Path\Transform\Window;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Order;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * The rows of one partition of a window, in the order of the window, and the window functions computed over them.
 *
 * Rows equal on every ORDER BY expression are peers; without ORDER BY every row is a peer of every
 * other. ROW_NUMBER numbers the rows from 1; RANK is the number of the first peer, DENSE_RANK the
 * number of the group of peers; PERCENT_RANK is (RANK - 1) / (rows - 1), 0 for one row; CUME_DIST
 * is the number of the last peer over the number of rows; NTILE(n) splits the rows in n buckets
 * whose sizes differ by at most one, the larger first. LAG and LEAD read the row that many rows
 * before or after the current one, or the default, NULL when there is none. FIRST_VALUE,
 * LAST_VALUE and NTH_VALUE read the first, last and nth row of the frame, NULL when the frame has
 * no such row; an aggregate folds the rows of the frame.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-function-descriptions.html,
 * https://dev.mysql.com/doc/refman/8.4/en/window-functions-frames.html.
 *
 * @visibility MySqlMemory
 */
final class Partition
{
    /**
     * @var list<int> The index of the first peer of each row
     */
    private array $first = [];

    /**
     * @var list<int> The index of the last peer of each row
     */
    private array $last = [];

    /**
     * @var list<int> The number of the group of peers of each row, from 1
     */
    private array $groups = [];

    /**
     * @var array<int, array<int, true>> The rows each aggregate has folded already, by the object id of the call
     */
    private array $folded = [];

    private int $loaded = -1;

    /**
     * @var array<int, Sliding> Incremental exact SUM and AVG folds
     */
    private array $sliding = [];

    /**
     * @var array<int, array{int, int|float|string|null}> The current FIRST_VALUE or NTH_VALUE selection per call
     */
    private array $selected = [];

    /**
     * @var array<int, array<int, int|float|string|null>> Argument values consumed by LAST_VALUE per call
     */
    private array $lastValues = [];

    /**
     * @param list<list<int|float|string|null>> $rows The rows of the partition, in the order of the window
     * @param list<list<int|float|string|null>> $keys The values of the ORDER BY expressions of each row
     * @param Window $window The window
     */
    public function __construct(public array $rows, public readonly array $keys, public readonly Window $window)
    {
        $group = 0;
        $start = 0;
        foreach (array_keys($rows) as $row) {
            if ($row === 0 || !$this->peers($row - 1, $row)) {
                $group++;
                $start = $row;
            }
            $this->first[] = $start;
            $this->groups[] = $group;
        }
        $ends = [];
        $end = count($rows) - 1;
        for ($row = $end; $row >= 0; $row--) {
            if ($row < $end && !$this->peers($row, $row + 1)) {
                $end = $row;
            }
            $ends[] = $end;
        }
        $this->last = array_reverse($ends);
    }

    /**
     * Tells whether two rows are equal on every ORDER BY expression.
     */
    public function peers(int $left, int $right): bool
    {
        foreach ($this->window->order as $position => [$key]) {
            if (Order::compare($this->keys[$left][$position], $this->keys[$right][$position], $key->domain()) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Answers the values of the window functions for a row.
     *
     * @return list<int|float|string|null>
     * @throws \MySqlMemory\Error\SqlError When computing a value is an error
     */
    public function values(int $row, Frame $frame): array
    {
        $frame->row = $this->rows[$row];
        $this->load($this->frontier($row, $frame), $frame);
        $values = [];
        foreach ($this->window->functions as $analytic) {
            $frame->row = $this->rows[$row];
            $values[] = $this->value($analytic, $row, $frame);
        }

        return $values;
    }

    /**
     * Answers the last argument row needed before producing one output row.
     */
    public function frontier(int $row, Frame $frame): int
    {
        if ($this->window->buffered()) {
            return count($this->rows) - 1;
        }
        if (!$this->window->framed()) {
            return $row;
        }
        [, $end] = $this->extent($row, $frame);

        return max($row, $end);
    }

    /**
     * Tells whether reading this frame crosses the partition boundary.
     * The first row of the next partition has its argument effects already applied.
     */
    public function lookahead(int $row): bool
    {
        $end = $this->window->frame->end;

        return $this->window->buffered() || ($this->window->framed() && ($end->kind === FrameBoundKind::UnboundedFollowing || ($end->kind === FrameBoundKind::Following && $end->rows >= count($this->rows) - $row)));
    }

    /**
     * Materializes effectful arguments only as far as the current frame requires.
     * The input may already be sorted without evaluating these expressions.
     */
    public function load(int $position, Frame $frame): void
    {
        while ($this->loaded < $position) {
            $index = ++$this->loaded;
            $frame->row = $this->rows[$index];
            $this->rows[$index] = [...array_slice($this->rows[$index], 0, $this->window->input->width()), ...array_map(static fn ($argument) => $argument->evaluate($frame), [...$this->window->arguments, ...$this->window->copies])];
        }
    }

    /**
     * Answers the arguments and ordinary copies of the latest loaded row.
     *
     * @return list<int|float|string|null>
     */
    public function captured(): array
    {
        return $this->loaded < 0 ? [] : array_slice($this->rows[$this->loaded], $this->window->input->width());
    }

    /**
     * Answers the value of one window function for a row.
     *
     * @throws \MySqlMemory\Error\SqlError When computing a value is an error
     */
    public function value(Analytic $analytic, int $row, Frame $frame): int|float|string|null
    {
        $count = count($this->rows);

        return match ($analytic->kind) {
            WindowFunctionKind::RowNumber => $row + 1,
            WindowFunctionKind::Rank => $this->first[$row] + 1,
            WindowFunctionKind::DenseRank => $this->groups[$row],
            WindowFunctionKind::PercentRank => $count > 1 ? (float) ($this->first[$row] / ($count - 1)) : 0.0,
            WindowFunctionKind::CumulativeDistribution => (float) (($this->last[$row] + 1) / $count),
            WindowFunctionKind::Tile => $this->tile($row, $analytic->count),
            WindowFunctionKind::Lead, WindowFunctionKind::Lag => $this->shifted($analytic, $row, $frame),
            WindowFunctionKind::FirstValue, WindowFunctionKind::LastValue, WindowFunctionKind::NthValue => $this->framed($analytic, $row, $frame),
            null => $this->aggregate($analytic, $row, $frame),
        };
    }

    /**
     * Answers the bucket of NTILE a row falls in: the rows split in buckets whose sizes differ by at most one, the larger first.
     */
    public function tile(int $row, int $buckets): int
    {
        $count = count($this->rows);
        if ($buckets >= $count) {
            return $row + 1;
        }
        $size = intdiv($count, $buckets);
        $larger = ($count % $buckets) * ($size + 1);

        return $row < $larger ? intdiv($row, $size + 1) + 1 : intdiv($row - $larger, $size) + intdiv($larger, $size + 1) + 1;
    }

    /**
     * Answers LEAD or LAG for a row: the value of the row that many rows after or before it, or else the default evaluated for the row itself.
     *
     * @throws \MySqlMemory\Error\SqlError When evaluating the value is an error
     */
    public function shifted(Analytic $analytic, int $row, Frame $frame): int|float|string|null
    {
        $count = count($this->rows);
        $target = $analytic->kind === WindowFunctionKind::Lag ? ($analytic->count > $row ? -1 : $row - $analytic->count) : ($analytic->count >= $count - $row ? $count : $row + $analytic->count);
        $argument = $target >= 0 && $target < $count ? $analytic->arguments[0] : ($analytic->arguments[1] ?? null);
        if ($argument === null) {
            return null;
        }
        $frame->row = $this->rows[$target >= 0 && $target < $count ? $target : $row];
        $value = $argument->evaluate($frame);
        $frame->row = $this->rows[$row];

        return self::converted($value, $argument->domain(), $analytic->domain, $frame);
    }

    /**
     * Answers FIRST_VALUE, LAST_VALUE or NTH_VALUE for a row: the value of that row of its frame, or NULL.
     *
     * @throws \MySqlMemory\Error\SqlError When evaluating the value is an error
     */
    public function framed(Analytic $analytic, int $row, Frame $frame): int|float|string|null
    {
        [$start, $end] = $this->extent($row, $frame);
        $target = $start;
        if ($analytic->kind === WindowFunctionKind::LastValue) {
            $target = $end;
        }
        if ($analytic->kind === WindowFunctionKind::NthValue) {
            $target = $analytic->count - 1 > $end - $start ? $end + 1 : $start + $analytic->count - 1;
        }
        if ($start > $end || $target > $end) {
            return null;
        }
        $argument = $analytic->arguments[0];
        $value = $this->selected($analytic, $start, $target, $frame);
        $frame->row = $this->rows[$row];

        return self::converted($value, $argument->domain(), $analytic->domain, $frame);
    }

    /**
     * Reads only the argument positions consumed by a framed value function.
     * FIRST_VALUE and NTH_VALUE retain their selection; when it moves they read
     * the previous selection before the new one. LAST_VALUE consumes each frame
     * member once. Empty frames read nothing. Verified through warning counts
     * over fixed and moving frames on MySQL 8.0, 8.4 and 9.1.
     */
    public function selected(Analytic $analytic, int $start, int $target, Frame $frame): int|float|string|null
    {
        $id = spl_object_id($analytic);
        $argument = $analytic->arguments[0];
        if ($analytic->kind === WindowFunctionKind::LastValue) {
            for ($position = $start; $position <= $target; $position++) {
                if (!array_key_exists($position, $this->lastValues[$id] ?? [])) {
                    $frame->row = $this->rows[$position];
                    $this->lastValues[$id][$position] = $argument->evaluate($frame);
                }
            }

            return $this->lastValues[$id][$target];
        }
        $bounds = $this->window->frame;
        if ($bounds->start->kind !== FrameBoundKind::UnboundedPreceding && in_array($bounds->end->kind, [FrameBoundKind::Following, FrameBoundKind::UnboundedFollowing], true)) {
            $frame->row = $this->rows[$target];

            return $argument->evaluate($frame);
        }
        $previous = $this->selected[$id] ?? null;
        if ($previous !== null && $previous[0] === $target) {
            return $previous[1];
        }
        if ($previous !== null) {
            $frame->row = $this->rows[$previous[0]];
            $argument->evaluate($frame);
        }
        $frame->row = $this->rows[$target];
        $value = $argument->evaluate($frame);
        $this->selected[$id] = [$target, $value];

        return $value;
    }

    /**
     * Converts a value a function reads into the type of its result: a date or a time of a datetime result as the datetime it is, any other value as the run-time kind of the result holds it.
     */
    public static function converted(int|float|string|null $value, Domain $from, Domain $to, Frame $frame): int|float|string|null
    {
        if ($value !== null && $to->kind->temporal() && $from->kind !== $to->kind) {
            return (new Moments())->convert($value, $from, $to, $frame->context);
        }

        return Coerce::to($value, $from, $to, $frame->context);
    }

    /**
     * Answers an aggregate for a row: the fold of the rows of its frame.
     *
     * The server adds each row to an aggregate once as the frame moves, so a row folded again for a
     * later frame does not warn again (verified on a live 8.4 server).
     *
     * @throws \MySqlMemory\Error\SqlError When evaluating an argument is an error
     */
    public function aggregate(Analytic $analytic, int $row, Frame $frame): int|float|string|null
    {
        if ($analytic->accumulation === null) {
            return null;
        }
        if (Sliding::supports($analytic->accumulation)) {
            $id = spl_object_id($analytic);
            $this->sliding[$id] ??= new Sliding($analytic->accumulation);

            return $this->sliding[$id]->value($this, $row, $frame);
        }
        $accumulator = $analytic->accumulation->start();
        [$start, $end] = $this->extent($row, $frame);
        $context = $frame->context;
        $quiet = new Frame(new Context($context->modes, new Diagnostics(), $context->variables, $context->started));
        $id = spl_object_id($analytic);
        for ($index = $start; $index <= $end; $index++) {
            $folding = isset($this->folded[$id][$index]) ? $quiet : $frame;
            $folding->row = $this->rows[$index];
            $accumulator->add($folding);
            $this->folded[$id][$index] = true;
        }
        $frame->row = $this->rows[$row];

        return $accumulator->result($frame);
    }

    /**
     * Answers the first and the last row of the frame of a row; the first is after the last when the frame is empty.
     *
     * @return array{int, int}
     * @throws \MySqlMemory\Error\SqlError When computing a boundary is an error
     */
    public function extent(int $row, Frame $frame): array
    {
        $frameClause = $this->window->frame;
        $count = count($this->rows);
        if ($frameClause->unit === FrameUnit::Rows) {
            return [max(0, $frameClause->start->rows($row, $count)), min($count - 1, $frameClause->end->rows($row, $count))];
        }

        return [$this->edge($frameClause->start, $row, $frame, true), $this->edge($frameClause->end, $row, $frame, false)];
    }

    /**
     * Answers the row a boundary of a RANGE frame lies at: the first row of the frame for its start, the last for its end.
     *
     * A row is within an offset when its ordering value lies between the value of the current row
     * and that value moved by the offset; NULL lies before every value in the order of the window.
     *
     * @throws \MySqlMemory\Error\SqlError When computing the boundary is an error
     */
    public function edge(Bound $bound, int $row, Frame $frame, bool $start): int
    {
        $count = count($this->rows);
        if ($bound->kind === FrameBoundKind::UnboundedPreceding || $bound->kind === FrameBoundKind::UnboundedFollowing) {
            return $bound->kind === FrameBoundKind::UnboundedPreceding ? 0 : $count - 1;
        }
        $limit = $bound->limit?->evaluate($frame);
        if ($bound->limit === null || $limit === null || $this->keys[$row][0] === null) {
            return $start ? $this->first[$row] : $this->last[$row];
        }
        $direction = $this->window->order[0][1] ? -1 : 1;
        $domain = $bound->limit->domain();
        if ($start) {
            for ($index = 0; $index < $count; $index++) {
                if ($direction * $this->compare($this->keys[$index][0], $limit, $domain) >= 0) {
                    return $index;
                }
            }

            return $count;
        }
        for ($index = $count - 1; $index >= 0; $index--) {
            if ($direction * $this->compare($this->keys[$index][0], $limit, $domain) <= 0) {
                return $index;
            }
        }

        return -1;
    }

    /**
     * Compares an ordering value with where a boundary lies, a date read as the start of its day.
     */
    public function compare(int|float|string|null $value, int|float|string $limit, Domain $domain): int
    {
        if ($value !== null && ($domain->kind === Kind::DateTime || $domain->kind === Kind::Date)) {
            return strcmp(self::moment((string) $value), self::moment((string) $limit));
        }

        return Order::compare($value, $limit, $domain);
    }

    /**
     * Writes a date or a datetime with the time and six fractional digits, so that two moments compare as their text.
     */
    public static function moment(string $value): string
    {
        $value = strlen($value) <= 10 ? $value . ' 00:00:00' : $value;

        return str_contains($value, '.') ? str_pad($value, 26, '0') : $value . '.000000';
    }
}
