<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Table;

use Override;

/**
 * PostgreSQL persistence and table storage declarations.
 *
 * @visibility public
 * @example Reading PostgreSQL table properties
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TEMP TABLE t(id INTEGER) USING heap WITH (fillfactor = 70) ON COMMIT DROP')->tables[0];
 *     $table->properties instanceof \SqlSemantics\Schema\Table\PostgreSqlProperties // => true
 *     $table->properties->persistence // => \SqlSemantics\Schema\Table\Persistence::Temporary
 *     $table->properties->onCommit // => \SqlSemantics\Schema\Table\CommitAction::Drop
 *     $table->properties->accessMethod // => 'heap'
 *     count($table->properties->storageParameters) // => 1
 *     $table->properties->partitioning // => null
 * @example Reading the inherited parents
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE a(x INTEGER)', 'CREATE TABLE c(y INTEGER) INHERITS (a)')->tables[1];
 *     $table->properties->parents[0]->parts // => ['a']
 * @example Rejecting a partitioned table that inherits
 *     $key = new \SqlSemantics\Schema\Partition\PartitionKey(\SqlSemantics\Model\Expression::literal(1, \SqlSemantics\Dialect::PostgreSql));
 *     new \SqlSemantics\Schema\Table\PostgreSqlProperties(partitioning: new \SqlSemantics\Schema\Partition\PartitionScheme(\SqlSemantics\Schema\Partition\PartitionStrategy::Hash, [$key]), parents: [new \SqlSemantics\Model\Relation\QualifiedName(['a'])]); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class PostgreSqlProperties implements Properties
{
    /**
     * Constructs a valid declaration; a partitioned table stores no rows, so it takes no storage parameters, and it cannot inherit;
     * only a temporary table has a commit action other than keeping its rows.
     *
     * @param list<\SqlSemantics\Schema\Storage\Parameter> $storageParameters
     * @param \SqlSemantics\Schema\Partition\PartitionScheme|null $partitioning Strategy and keys of a partitioned table (PARTITION BY)
     * @param list<\SqlSemantics\Model\Relation\QualifiedName> $parents Distinct tables whose columns this table inherits (INHERITS)
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        public readonly Persistence $persistence = Persistence::Permanent,
        public readonly CommitAction $onCommit = CommitAction::PreserveRows,
        public readonly ?string $accessMethod = null,
        public readonly ?string $tablespace = null,
        public readonly array $storageParameters = [],
        public readonly ?\SqlSemantics\Schema\Partition\PartitionScheme $partitioning = null,
        public readonly array $parents = [],
    ) {
        \SqlSemantics\Model\Validation\Collections::objects($storageParameters, \SqlSemantics\Schema\Storage\Parameter::class);
        if ($onCommit !== CommitAction::PreserveRows && $persistence !== Persistence::Temporary) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('Only a temporary table deletes its rows or itself at commit.');
        }
        if ($partitioning !== null && $storageParameters !== []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A partitioned table cannot declare storage parameters.');
        }
        \SqlSemantics\Model\Validation\Collections::objects($parents, \SqlSemantics\Model\Relation\QualifiedName::class);
        if ($partitioning !== null && $parents !== []) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A partitioned table cannot inherit from another table.');
        }
        $spellings = [];
        foreach ($parents as $parent) {
            \SqlSemantics\Model\Definition\Catalog\CatalogInvariant::name($parent, 3);
            $spellings[] = implode('.', $parent->parts);
        }
        if (count(array_unique($spellings)) !== count($spellings)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A table inherits from each parent once.');
        }
    }
    /**
     * Returns the SQL dialect that defines these options.
     */
    #[Override]
    public function dialect(): \SqlSemantics\Dialect
    {
        return \SqlSemantics\Dialect::PostgreSql;
    }
}
