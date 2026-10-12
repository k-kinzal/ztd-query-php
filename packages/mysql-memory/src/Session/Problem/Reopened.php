<?php

declare(strict_types=1);

namespace MySqlMemory\Session\Problem;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Statement;

/**
 * Refuses a statement that names a temporary table more than once (ER_CANT_REOPEN_TABLE).
 *
 * A statement can open a temporary table once: a self-join, a subquery or a set operation that
 * reads it again fails, naming the first occurrence by its alias, else by its name (verified on a
 * live 8.4 server). Resolved common tables shadow session table names, but each reference
 * to a common table opens its temporary inputs, even if its results are materialized.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/temporary-table-problems.html.
 *
 * @visibility MySqlMemory
 */
final class Reopened
{
    /**
     * Refuses a statement that names a temporary table of the session more than once.
     *
     * @throws SqlError When it does
     */
    public function check(Statement $statement, Session $session, \SqlSemantics\Statement\Fact\Facts $facts): void
    {
        if ($session->temporaries->tables === []) {
            return;
        }
        $seen = [];
        $walker = new Walker();
        foreach ([...$this->reads($statement, $facts), ...$walker->find($statement, WriteTarget::class)] as $reference) {
            $table = $this->temporary($reference, $session);
            if ($table === null) {
                continue;
            }
            $id = spl_object_id($table);
            if (isset($seen[$id])) {
                throw SchemaError::CantReopenTable->error($seen[$id]);
            }
            $seen[$id] = $reference->alias->value ?? $reference->name->name->value;
        }
    }

    /**
     * Visits FROM sources before scalar subqueries and expands each resolved CTE use.
     * Unused definitions are skipped; recursive self references do not reopen the base input.
     *
     * @param array<int, true> $active The common tables already expanding on this branch
     *
     * @return list<TableReference>
     */
    public function reads(\SqlSemantics\Statement\Node $statement, \SqlSemantics\Statement\Fact\Facts $facts, array $active = []): array
    {
        if ($statement instanceof TableReference) {
            $resolution = $facts->covers($statement) ? $facts->relation($statement)->table : null;
            if ($resolution instanceof \SqlSemantics\Statement\Reference\Table\CommonTable && $resolution->definition instanceof \SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression) {
                $id = spl_object_id($resolution->definition);
                return isset($active[$id]) ? [] : $this->reads($resolution->definition->query, $facts, $active + [$id => true]);
            }
            return [$statement];
        }
        $properties = get_object_vars($statement);
        unset($properties['with']);
        if ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select) {
            $properties = ['from' => $statement->from] + $properties;
        } elseif ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Query\QueryExpression) {
            $properties = ['body' => $statement->body] + $properties;
        } elseif ($statement instanceof \SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation) {
            $properties = ['left' => $statement->left, 'right' => $statement->right] + $properties;
        }
        $references = [];
        $pending = array_reverse(array_values($properties));
        while ($pending !== []) {
            $node = array_pop($pending);
            if (is_array($node)) {
                array_push($pending, ...array_reverse(array_values($node)));
            } elseif ($node instanceof \SqlSemantics\Statement\Node) {
                array_push($references, ...$this->reads($node, $facts, $active));
            }
        }
        return $references;
    }

    /**
     * Answers the temporary table a reference names, or null.
     */
    public function temporary(TableReference|WriteTarget $reference, Session $session): ?StoredTable
    {
        return $session->temporaries->table($reference->name->schema->value ?? $session->variables->database, $reference->name->name->value);
    }
}
