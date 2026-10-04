<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Definition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Conditions;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Targets;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Derives and writes CREATE INDEX.
 *
 * Rule: PG-INDEX-001. The indexed table is resolved by PG-TABLE-TARGET-001;
 * inside CREATE SCHEMA an unqualified table is looked up in the schema being
 * created. The resolution is recorded as the relation fact of the statement.
 * The keys and included columns are derived where the table is the only
 * visible relation; the predicate of a partial index is a condition
 * (PG-TABLE-CONDITION-001). Source:
 * https://www.postgresql.org/docs/17/sql-createindex.html,
 * https://www.postgresql.org/docs/17/sql-createschema.html. Termination: one
 * pass over the keys. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class Indexes
{
    /**
     * Derives an index; `$schema` is the schema of an enclosing CREATE SCHEMA.
     */
    public function derive(CreateIndex $index, Derivation $derivation, ?Name $schema): void
    {
        $targets = new Targets();
        $name = $this->located($index->table->name, $schema);
        $fact = $derivation->target($index, $targets->resolve($derivation, $name));
        $scope = $targets->scope($derivation, $index, $name, $fact->shape, $targets->implicit($fact));
        foreach ([...$index->elements, ...$index->included] as $element) {
            $element->deriveClause($derivation, $scope);
        }
        foreach ($index->options as $option) {
            $option->deriveClause($derivation, $scope);
        }
        if ($index->where !== null) {
            (new Conditions())->derive($derivation, $index->where, $scope, 'WHERE');
        }
    }

    /**
     * Answers a name as an element of CREATE SCHEMA reads it: an unqualified name is in the given schema.
     */
    public function located(QualifiedName $name, ?Name $schema): QualifiedName
    {
        return $schema === null || $name->schema !== null ? $name : new QualifiedName($name->name, $schema);
    }

    /**
     * Writes CREATE INDEX.
     */
    public function write(Output $out, CreateIndex $index): void
    {
        $out->keyword('CREATE');
        if ($index->unique) {
            $out->keyword('UNIQUE');
        }
        $out->keyword('INDEX');
        if ($index->concurrently) {
            $out->keyword('CONCURRENTLY');
        }
        if ($index->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        if ($index->name !== null) {
            $out->name($index->name);
        }
        $out->keyword('ON')->node($index->table);
        if ($index->method !== null) {
            $out->keyword('USING')->name($index->method);
        }
        $out->symbol('(')->list($index->elements)->symbol(')');
        if ($index->included !== []) {
            $out->keyword('INCLUDE')->symbol('(')->list($index->included)->symbol(')');
        }
        $writing = new Writing();
        $writing->nullTreatment($out, $index->nullsDistinct);
        if ($index->options !== []) {
            $out->keyword('WITH');
            $writing->definitions($out, $index->options);
        }
        if ($index->tablespace !== null) {
            $out->keyword('TABLESPACE')->name($index->tablespace);
        }
        if ($index->where !== null) {
            $out->keyword('WHERE')->node($index->where);
        }
    }
}
