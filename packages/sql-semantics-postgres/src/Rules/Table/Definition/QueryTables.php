<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Definition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\SystemColumns;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Persistence;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionProblem;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Problem\DefinitionRule;
use SqlSemantics\Platform\PostgreSql\Rules\Typing\DeclaredTypes;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Derives the declaration of a relation defined by a query: CREATE TABLE AS, CREATE VIEW, CREATE MATERIALIZED VIEW.
 *
 * Rule: PG-QUERY-TABLE-001. The relation has one column per output column of
 * the query, in order; the written column names rename the first columns,
 * and more names than columns is an error. A column of type `unknown` (an
 * untyped string constant) is `text`. The columns of a table made by CREATE
 * TABLE AS have no constraints and can be NULL; the columns of a view or a
 * materialized view hold exactly the rows of the query and keep its NULL
 * facts. A column whose type depends on declarations the context does not
 * hold is declared with the type those inputs settle (PG-DECLARED-TYPE-001);
 * the declaration is complete only up to the first output whose name or
 * position the context cannot know (an open row) or whose type is invalid. A temporary relation with an unqualified
 * name belongs to `pg_temp`. Tables and materialized views have the system
 * columns; views do not. Two columns of one name are an error.
 * Source: https://www.postgresql.org/docs/17/sql-createtableas.html,
 * https://www.postgresql.org/docs/17/sql-createview.html,
 * https://www.postgresql.org/docs/17/sql-creatematerializedview.html.
 * Termination: one pass over the output columns. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class QueryTables
{
    /**
     * Answers the name a relation is declared under; `$schema` is the schema of an enclosing CREATE SCHEMA.
     */
    public function name(QualifiedName $written, Persistence $persistence, ?Name $schema): QualifiedName
    {
        if ($written->schema !== null) {
            return $written;
        }
        if ($persistence->temporary()) {
            return new QualifiedName($written->name, new Name('pg_temp'));
        }

        return $schema === null ? $written : new QualifiedName($written->name, $schema);
    }

    /**
     * Builds the declaration of the relation a query defines and reports the problems of its column names.
     *
     * @param list<Name> $names The written column names
     * @param bool $system Whether the relation has the system columns
     * @param bool $nullable Whether every column can be NULL, as in a table; a view keeps the NULL facts of the query
     */
    public function table(Derivation $derivation, QualifiedName $name, QueryFact $fact, array $names, bool $system, bool $nullable, DefinitionRule $tooMany = DefinitionRule::TableColumnCount): Table
    {
        $columns = [];
        $complete = true;
        $seen = [];
        $types = new DeclaredTypes();
        foreach ($fact->projection as $position => $item) {
            $column = $item instanceof Field ? ($names[$position] ?? $item->name) : null;
            $type = $item instanceof Field ? $types->defined($item->type) : null;
            if (!$item instanceof Field || $column === null || $type === null) {
                $complete = false;
                break;
            }
            if (isset($seen[$column->value])) {
                $derivation->report(new DefinitionProblem(DefinitionRule::DuplicateColumn, $column));
            }
            $seen[$column->value] = true;
            $columns[] = new Column($column, $type, $nullable ? Nullability::Nullable : $item->nullability);
        }
        if ($fact->shape->complete() && count($names) > count($fact->projection)) {
            $derivation->report(new DefinitionProblem($tooMany));
        }

        return new Table($name, $derivation->context->profile, $columns, $system ? (new SystemColumns())->implicit() : [], $complete);
    }
}
