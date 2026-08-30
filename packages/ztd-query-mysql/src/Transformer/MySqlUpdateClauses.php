<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Statements\UpdateStatement;
use ZtdQuery\Platform\MySql\Dialect\MySqlComponentSql;

/**
 * Everything an UPDATE writes after what it assigns, written out for a SELECT.
 *
 * The statement that answers the rows an UPDATE would have changed reads
 * under the same condition, in the same order and up to the same limit, so
 * all of that is carried over unchanged.
 */
final class MySqlUpdateClauses
{
    /**
     * @param MySqlComponentSql $components Writes one component of a statement back out
     */
    public function __construct(private readonly MySqlComponentSql $components = new MySqlComponentSql())
    {
    }

    /**
     * Writes out the condition the statement changes rows under.
     *
     * The condition is read out of the statement as written where anything
     * could be read, because the parser does not always give back what it was
     * handed; where nothing could, the parser's reading is written out again.
     *
     * @param UpdateStatement $statement The statement, as the parser reads it
     * @param string|null $written The condition as the statement wrote it, where it could be read
     *
     * @return string The WHERE clause, or nothing where the statement writes none
     */
    public function whereClause(UpdateStatement $statement, ?string $written): string
    {
        $condition = $written ?? $this->components->condition($statement->where ?? [], $statement->build());

        return $condition === '' ? '' : ' WHERE ' . $condition;
    }

    /**
     * Writes out the order the statement changes rows in.
     *
     * @param UpdateStatement $statement The statement, as the parser reads it
     *
     * @return string The ORDER BY clause, or nothing where the statement writes none
     */
    public function orderClause(UpdateStatement $statement): string
    {
        $order = $statement->order ?? [];
        if ($order === []) {
            return '';
        }

        $parts = [];
        foreach ($order as $term) {
            $parts[] = $this->components->order($term, $statement->build());
        }

        return ' ORDER BY ' . implode(', ', $parts);
    }

    /**
     * Writes out how many rows the statement changes at most.
     *
     * @param UpdateStatement $statement The statement, as the parser reads it
     *
     * @return string The LIMIT clause, or nothing where the statement writes none
     */
    public function limitClause(UpdateStatement $statement): string
    {
        if ($statement->limit === null) {
            return '';
        }

        return ' LIMIT ' . $this->components->limit($statement->limit, $statement->build());
    }
}
