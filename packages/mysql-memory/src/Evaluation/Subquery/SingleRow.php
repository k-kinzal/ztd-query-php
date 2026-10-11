<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Subquery;

use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Reads the sole row of a multi-column subquery, keeping an empty result distinct from a row of NULLs.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/row-subqueries.html.
 *
 * @visibility MySqlMemory
 */
final class SingleRow
{
    private ?Frame $frame = null;

    /**
     * @param Rows $rows The planned subquery
     * @param bool $once Whether its result is constant for this statement
     * @param list<Evaluable> $projection Output expressions readable without an input row
     * @param list<int|float|string|null> $initial The projection's input state before a row is read
     */
    public function __construct(public readonly Rows $rows, public readonly bool $once = false, public readonly array $projection = [], public readonly array $initial = [])
    {
    }

    /**
     * Answers the stored column domains; string-to-number reads of a row subquery are quiet.
     *
     * @return list<Domain>
     */
    public function domains(): array
    {
        return array_map(static fn (Domain $domain): Domain => $domain->kind === Kind::String ? $domain->withQuiet() : $domain, $this->rows->plan->domains);
    }

    /**
     * Reads the row, caching an independent subquery within its statement context.
     *
     * @return list<int|float|string|null> An empty list means the subquery returned no row
     */
    public function read(Frame $frame): array
    {
        if (!$this->once) {
            return $this->fetch($frame);
        }
        $kept = $frame->context->kept;
        if (!isset($kept[$this])) {
            $kept[$this] = $this->fetch($frame);
        }

        return $kept[$this];
    }

    /**
     * Reads up to two rows to enforce the single-row cardinality requirement.
     *
     * @return list<int|float|string|null> An empty list means the subquery returned no row
     * @throws \MySqlMemory\Error\SqlError When the subquery returns more than one row
     */
    public function fetch(Frame $frame): array
    {
        $this->frame = new Frame($frame->context, $this->initial, $frame);
        $iterator = $this->rows->in($this->frame);
        $row = $iterator->read();
        if ($row === null) {
            return [];
        }
        if ($iterator->read() !== null) {
            throw QueryError::SubqueryNotOneRow->error();
        }

        return array_slice($row, 0, count($this->rows->plan->domains));
    }

    /**
     * Reads a retained projection expression after an empty result, in its query's input state.
     */
    public function value(int $position, Frame $outer): int|float|string|null
    {
        return $this->projection[$position]->evaluate($this->frame ?? $outer->inner());
    }
}
