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
 *     $statement->from->indexHints // => []
 */
final class TableReference extends NamedTableReference
{
    /**
     * @var list<\SqlSemantics\Model\Query\Optimization\IndexHint> MySQL index hints, in written order
     */
    public readonly array $indexHints;

    /**
     * @param list<\SqlSemantics\Model\Query\Optimization\IndexHint> $indexHints MySQL index hints, in written order
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        string $id,
        string $scopeId,
        \SqlSemantics\Schema\TableDefinition $declaration,
        QualifiedName $name,
        ?string $alias,
        \SqlParser\Parser\Node $source,
        array $indexHints = [],
    ) {
        \SqlSemantics\Model\Validation\Collections::objects($indexHints, \SqlSemantics\Model\Query\Optimization\IndexHint::class);
        parent::__construct($id, $scopeId, $declaration, $name, $alias, $source);
        $this->indexHints = $indexHints;
    }

    /**
     * Retains the table's descendant selection and index hints while changing its owning scope.
     */
    #[Override]
    public function withScope(string $scopeId): static
    {
        return new self($this->id, $scopeId, $this->declaration, $this->name, $this->alias, $this->source, $this->indexHints);
    }

}
