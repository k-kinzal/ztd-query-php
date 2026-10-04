<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Catalog;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Statement\Table\SchemaElement;
use SqlSemantics\Rendering\Output;
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
 * declared in the new schema. The context declares relations only, so an
 * element does not see the relations its sibling elements create. This
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
     * @param Statement $element The element
     * @param Name $schema The schema being created
     */
    public function __construct(private readonly Statement $element, private readonly Name $schema)
    {
    }

    /**
     * Answers the context the elements of a new schema are read in: the same declarations, with the schema searched before the written path.
     */
    public function context(AnalysisContext $outer): AnalysisContext
    {
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

        return new AnalysisContext($outer->profile, $path, $outer->tables, $outer->complete, $outer->relationNames, $outer->columnNames, $outer->declarationSchema);
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
