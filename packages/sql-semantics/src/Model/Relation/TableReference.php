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
 *     [$statement->from->partitions, $statement->from->indexing] // => [null, null]
 */
final class TableReference extends NamedTableReference
{
    /**
     * @var list<\SqlSemantics\Model\Query\Optimization\IndexHint> MySQL index hints, in written order
     */
    public readonly array $indexHints;

    /**
     * Constructs a table occurrence; index hints and explicit partitions are MySQL clauses and an index directive is a SQLite clause, so a directive never accompanies them or a sample.
     * @param list<\SqlSemantics\Model\Query\Optimization\IndexHint> $indexHints MySQL index hints, in written order
     * @param \SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions|null $partitions MySQL PARTITION selection restricting the rows to the named partitions
     * @param \SqlSemantics\Model\Query\Optimization\IndexDirective|null $indexing SQLite INDEXED BY or NOT INDEXED
     * @param \SqlSemantics\Model\Query\Sampling\TableSample|null $sample TABLESAMPLE clause reading a sample of the rows
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
        public readonly ?\SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions $partitions = null,
        public readonly ?\SqlSemantics\Model\Query\Optimization\IndexDirective $indexing = null,
        ?\SqlSemantics\Model\Query\Sampling\TableSample $sample = null,
    ) {
        \SqlSemantics\Model\Validation\Collections::objects($indexHints, \SqlSemantics\Model\Query\Optimization\IndexHint::class);
        if ($indexing !== null && ($indexHints !== [] || $partitions !== null || $sample !== null)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A SQLite index directive does not combine with MySQL table clauses or a sample.');
        }
        parent::__construct($id, $scopeId, $declaration, $name, $alias, $source, $sample);
        $this->indexHints = $indexHints;
    }

    /**
     * Retains the table's descendant selection and access clauses while changing its owning scope.
     */
    #[Override]
    public function withScope(string $scopeId): static
    {
        return new self($this->id, $scopeId, $this->declaration, $this->name, $this->alias, $this->source, $this->indexHints, $this->partitions, $this->indexing, $this->sample);
    }

}
