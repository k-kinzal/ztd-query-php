<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation;

use Override;

/**
 * A PostgreSQL table occurrence that excludes inherited and partition descendant rows.
 * @visibility public
 * @example Inspecting the selected row scope
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('TABLE ONLY t');
 *     $statement->from instanceof \SqlSemantics\Model\Relation\OnlyTableReference // => true
 */
final class OnlyTableReference extends NamedTableReference
{
    /**
     * Requires a PostgreSQL table declaration independently of any owning statement.
     */
    public function __construct(string $id, string $scopeId, \SqlSemantics\Schema\TableDefinition $declaration, QualifiedName $name, ?string $alias, \SqlParser\Parser\Node $source)
    {
        parent::__construct($id, $scopeId, $declaration, $name, $alias, $source);
        \SqlSemantics\Model\Validation\StatementOperands::relation($this, \SqlSemantics\Dialect::PostgreSql);
    }

    /**
     * Retains the table's descendant selection while changing its owning scope.
     */
    #[Override]
    public function withScope(string $scopeId): static
    {
        return new self($this->id, $scopeId, $this->declaration, $this->name, $this->alias, $this->source);
    }

}
