<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Catalog;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Statement\Table\CreateTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex;
use SqlSemantics\Platform\PostgreSql\Statement\Table\SchemaElement;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Sequence\CreateSequence;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\CreateConstraintTrigger;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger\CreateTrigger;
use SqlSemantics\Platform\PostgreSql\Statement\Table\View\CreateView;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Statement;

/**
 * An element of CREATE SCHEMA as the server reads it: created in the new schema, with that schema searched first.
 *
 * Rule: PG-SCHEMA-ELEMENT-001. Scope: the `schema_stmt` elements of
 * `CreateSchemaStmt`. The server sets the search path to the new schema
 * followed by the previous path while it runs the elements ("the
 * subcommands are treated essentially the same as separate commands issued
 * after creating the schema, except that ... the search path is ... the new
 * schema first"), so an unqualified name in an element finds an object of
 * the new schema before any other; the temporary schema and `pg_catalog`
 * stay implicit before it. An unqualified object the element creates is
 * declared in the new schema. The server does not run the elements in the
 * order written: `transformCreateSchemaStmtElements` (parse_utilcmd.c)
 * runs the sequences first, then the tables, views, indexes, triggers and
 * grants, each group in the order written; an element sees the relations
 * the elements run before it create. This
 * value is a working value of one derivation: it stands for the element
 * while the derivation runs under the other search settings and is not part
 * of the published statement.
 * Source: https://www.postgresql.org/docs/17/sql-createschema.html.
 * Termination: one pass over the search path. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class SchemaMember implements Statement
{
    /**
     * The step each kind of element runs in; any other element (GRANT) runs last.
     */
    public const STEPS = [
        CreateSequence::class => 0,
        CreateTable::class => 1,
        CreateView::class => 2,
        CreateIndex::class => 3,
        CreateTrigger::class => 4,
        CreateConstraintTrigger::class => 4,
    ];

    /**
     * @param Statement $element The element
     * @param Name $schema The schema being created
     */
    public function __construct(private readonly Statement $element, private readonly Name $schema)
    {
    }

    /**
     * Answers the step the server runs the element in: a lower step runs before a higher one.
     */
    public function step(): int
    {
        return self::STEPS[$this->element::class] ?? 5;
    }

    /**
     * Answers the context the elements of a new schema are read in: the same declarations and those the statement already provided, with the schema searched before the written path.
     *
     * @param list<Table> $provided The relations the statement declared before this element
     */
    public function context(AnalysisContext $outer, array $provided = []): AnalysisContext
    {
        $tables = $outer->tables;
        foreach ($provided as $table) {
            if (!in_array($table, $tables, true)) {
                $tables[] = $table;
            }
        }
        $path = [];
        $placed = false;
        foreach ($outer->searchPath as $searched) {
            if (!$placed && $searched->value !== 'pg_temp' && $searched->value !== 'pg_catalog') {
                $path[] = $this->schema;
                $placed = true;
            }
            $path[] = $searched;
        }
        if (!$placed) {
            $path[] = $this->schema;
        }

        return new AnalysisContext($outer->profile, $path, $tables, $outer->complete, $outer->relationNames, $outer->columnNames, $outer->declarationSchema, $outer->session);
    }

    /**
     * Derives the element as a member of the schema; a statement that creates nothing in it is derived as it is.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        if ($this->element instanceof SchemaElement) {
            $this->element->deriveElement($derivation, $this->schema);
        } else {
            $derivation->statement($this->element);
        }
    }

    /**
     * Writes the element.
     */
    public function render(Output $out): void
    {
        $out->node($this->element);
    }
}
