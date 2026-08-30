<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use ZtdQuery\Platform\MySql\Dialect\MySqlComponentSql;
use ZtdQuery\Platform\MySql\Parse\DmlWhereClauseExtractor;

/**
 * Everything a DELETE writes after its target, written out again for a SELECT.
 *
 * The statement that answers the rows a DELETE would have removed reads from
 * the same relations, under the same conditions, in the same order and up to
 * the same limit, so all of that is carried over unchanged. A USING clause
 * names the relations instead of FROM, so it is written where FROM would go.
 */
final class MySqlDeleteClauses
{
    /**
     * @param MySqlComponentSql $components Writes one component of a statement back out
     * @param DmlWhereClauseExtractor $conditions Reads the condition out of a statement as written
     */
    public function __construct(
        private readonly MySqlComponentSql $components,
        private readonly DmlWhereClauseExtractor $conditions = new DmlWhereClauseExtractor(),
    ) {
    }

    /**
     * Writes out everything the SELECT carries over from the DELETE.
     *
     * @param DeleteStatement $statement The statement, as the parser reads it
     * @param string $sql The statement, as written
     *
     * @return string The clauses, in the order a SELECT writes them
     */
    public function of(DeleteStatement $statement, string $sql): string
    {
        return $this->relationClause($statement, $sql)
            . $this->joinClause($statement, $sql)
            . ' ' . $this->whereClause($sql)
            . $this->orderClause($statement, $sql)
            . $this->limitClause($statement, $sql);
    }

    /**
     * Writes out the relations the statement reads from.
     *
     * @param DeleteStatement $statement The statement, as the parser reads it
     * @param string $sql The statement, as written
     *
     * @return string The FROM clause, or nothing where the statement reads from none
     */
    public function relationClause(DeleteStatement $statement, string $sql): string
    {
        $using = $statement->using ?? [];
        $relations = $using !== [] ? $using : ($statement->from ?? []);
        if ($relations === []) {
            return '';
        }

        $parts = [];
        foreach ($relations as $relation) {
            $parts[] = $this->components->expression($relation, $sql);
        }

        return ' FROM ' . implode(', ', $parts);
    }

    /**
     * Writes out the joins the statement makes.
     *
     * @param DeleteStatement $statement The statement, as the parser reads it
     * @param string $sql The statement, as written
     *
     * @return string The joins, or nothing where the statement makes none
     */
    public function joinClause(DeleteStatement $statement, string $sql): string
    {
        $joins = $statement->join ?? [];

        return $joins === [] ? '' : ' ' . $this->components->joins($joins, $sql);
    }

    /**
     * Writes out the condition the statement removes rows under.
     *
     * @param string $sql The statement, as written
     *
     * @return string The WHERE clause, or nothing where the statement writes none
     */
    public function whereClause(string $sql): string
    {
        $condition = $this->conditions->extract($sql);

        return $condition === null || $condition === '' ? '' : ' WHERE ' . $condition;
    }

    /**
     * Writes out the order the statement removes rows in.
     *
     * @param DeleteStatement $statement The statement, as the parser reads it
     * @param string $sql The statement, as written
     *
     * @return string The ORDER BY clause, or nothing where the statement writes none
     */
    public function orderClause(DeleteStatement $statement, string $sql): string
    {
        $order = $statement->order ?? [];
        if ($order === []) {
            return '';
        }

        $parts = [];
        foreach ($order as $term) {
            $parts[] = $this->components->order($term, $sql);
        }

        return ' ORDER BY ' . implode(', ', $parts);
    }

    /**
     * Writes out how many rows the statement removes at most.
     *
     * @param DeleteStatement $statement The statement, as the parser reads it
     * @param string $sql The statement, as written
     *
     * @return string The LIMIT clause, or nothing where the statement writes none
     */
    public function limitClause(DeleteStatement $statement, string $sql): string
    {
        return $statement->limit === null ? '' : ' LIMIT ' . $this->components->limit($statement->limit, $sql);
    }
}
