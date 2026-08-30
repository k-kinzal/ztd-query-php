<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Transformer;

use PhpMyAdmin\SqlParser\Statements\DeleteStatement;
use RuntimeException;

/**
 * Which tables a DELETE deletes from, and what the statement calls them.
 *
 * MySQL writes the tables being deleted from in two ways. A single-table
 * DELETE names one after FROM and deletes from that; a multi-table DELETE
 * names them before FROM, by whatever the FROM, JOIN or USING clauses called
 * them, and deletes from those. Working out which table a name stands for is
 * therefore a question about the whole statement, not about one clause.
 */
final class MySqlDeleteTargets
{
    /**
     * Answers the table a DELETE deletes from, and what the statement calls it.
     *
     * @param DeleteStatement $statement The statement, as the parser reads it
     *
     * @return array{name: string, alias: string} The table and the name it goes by
     *
     * @throws RuntimeException When the statement names a table that stands for nothing
     */
    public function of(DeleteStatement $statement): array
    {
        $named = $this->firstNamedAlias($statement);
        if ($named === null || $named === '') {
            return $this->firstRelation($statement);
        }

        return ['name' => $this->tableNamed($statement, $named) ?? 'unknown', 'alias' => $named];
    }

    /**
     * Answers the name the statement writes before FROM, where it writes one.
     *
     * @param DeleteStatement $statement The statement, as the parser reads it
     *
     * @return string|null The name, or null where the statement writes none
     */
    public function firstNamedAlias(DeleteStatement $statement): ?string
    {
        $columns = $statement->columns ?? [];
        if ($columns === []) {
            return null;
        }

        return DeleteTransformer::exprTable($columns[0]);
    }

    /**
     * Answers every name the statement writes before FROM.
     *
     * @param DeleteStatement $statement The statement, as the parser reads it
     *
     * @return list<string> The names, in the order they were written
     */
    public function namedAliases(DeleteStatement $statement): array
    {
        $aliases = [];
        foreach ($statement->columns ?? [] as $column) {
            $alias = DeleteTransformer::exprTable($column);
            if ($alias !== null && $alias !== '') {
                $aliases[] = $alias;
            }
        }

        return $aliases;
    }

    /**
     * Answers the first table the statement reads from, and what it calls it.
     *
     * @param DeleteStatement $statement The statement, as the parser reads it
     *
     * @return array{name: string, alias: string} The table and the name it goes by
     *
     * @throws RuntimeException When the statement reads from something naming no table
     */
    public function firstRelation(DeleteStatement $statement): array
    {
        $from = $statement->from ?? [];
        if ($from === []) {
            return ['name' => 'unknown', 'alias' => 'unknown'];
        }

        $name = DeleteTransformer::exprTable($from[0]);
        if ($name === null || $name === '') {
            throw new RuntimeException('Delete target table could not be resolved.');
        }

        return ['name' => $name, 'alias' => DeleteTransformer::exprAlias($from[0]) ?? $name];
    }

    /**
     * Answers the table a name stands for, where any clause gives it one.
     *
     * @param DeleteStatement $statement The statement, as the parser reads it
     * @param string $alias Name written in the statement
     *
     * @return string|null The table, or null where no clause names it
     */
    public function tableNamed(DeleteStatement $statement, string $alias): ?string
    {
        foreach ($this->relations($statement) as $relation) {
            if (DeleteTransformer::exprAlias($relation) !== $alias) {
                continue;
            }
            $name = DeleteTransformer::exprTable($relation);
            if ($name !== null && $name !== '') {
                return $name;
            }
        }

        return null;
    }

    /**
     * Answers everything the statement reads from, in the order MySQL reads it.
     *
     * @param DeleteStatement $statement The statement, as the parser reads it
     *
     * @return list<\PhpMyAdmin\SqlParser\Components\Expression> The relations
     */
    public function relations(DeleteStatement $statement): array
    {
        $relations = array_values($statement->from ?? []);
        foreach ($statement->join ?? [] as $join) {
            if ($join->expr !== null) {
                $relations[] = $join->expr;
            }
        }
        foreach ($statement->using ?? [] as $using) {
            $relations[] = $using;
        }

        return $relations;
    }
}
