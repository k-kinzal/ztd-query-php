<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Derives and writes the column lists and index parameters of UNIQUE, PRIMARY KEY, EXCLUDE and FOREIGN KEY table constraints.
 *
 * Rule: PG-KEY-CLAUSE-001. Each named column must be a column of the table
 * the constraint belongs to, which is the visible relation of the
 * environment; a column the complete row shape lacks is reported (PG-KEY-COLUMNS-001).
 * The storage parameters are derived in the same environment. Source:
 * https://www.postgresql.org/docs/17/sql-createtable.html. Termination: one
 * pass over the names. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class KeyClauses
{
    /**
     * Checks the named columns and derives the parameters.
     *
     * @param list<Name> $columns
     * @param list<Definition> $options
     */
    public function derive(Derivation $derivation, Environment $environment, array $columns, array $options): void
    {
        foreach ($environment->relations as $relation) {
            (new KeyColumns())->report($derivation, $columns, $relation->shape, DefinitionRule::MissingKeyColumn);
        }
        foreach ($options as $option) {
            $option->deriveClause($derivation, $environment);
        }
    }

    /**
     * Writes the key columns, the included columns and the index parameters, each when given.
     *
     * @param list<Name> $columns
     * @param list<Name> $included
     * @param list<Definition> $options
     */
    public function write(Output $out, array $columns, array $included, array $options, ?Name $tablespace): void
    {
        $writing = new Writing();
        if ($columns !== []) {
            $writing->parenthesized($out, $columns);
        }
        if ($included !== []) {
            $out->keyword('INCLUDE');
            $writing->parenthesized($out, $included);
        }
        $writing->indexParameters($out, $options, $tablespace);
    }
}
