<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation;

use Override;

/**
 * A named table using the dialect's normal row scope, including PostgreSQL descendants.
 * @visibility public
 * @example Inspecting a named table
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('TABLE t');
 *     $statement->from instanceof \SqlSemantics\Model\Relation\TableReference // => true
 */
final class TableReference extends NamedTableReference
{
    /**
     * Retains the table's descendant selection while changing its owning scope.
     */
    #[Override]
    public function withScope(string $scopeId): static
    {
        return new self($this->id, $scopeId, $this->declaration, $this->name, $this->alias, $this->source);
    }

}
