<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\MySql\Table;

use Override;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\MySqlTable\Partition\TablePartitioning;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A standalone PARTITION BY clause, the MySQL 5.6 and 5.7 parser entry the server uses to read stored partitioning.
 * @visibility public
 * @example Reading a stored partitioning clause
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('PARTITION BY KEY (id) PARTITIONS 4', strict: false);
 *     $statement->partitioning->partitionCount // => 4
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'PARTITION BY KEY(`id`) PARTITIONS 4'
 * @example Rejecting a release without the entry
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build();
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('PARTITION BY KEY (id)', strict: false);
 *     $other = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind('SELECT 1');
 *     new \SqlSemantics\Model\Statement\Definition\MySql\Table\PartitionSchemeStatement($other->origin, $statement->partitioning); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class PartitionSchemeStatement extends BoundStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(Origin $origin, public readonly TablePartitioning $partitioning)
    {
        TableInvariant::dialect($origin);
        TableInvariant::only($origin, ['mysql-5.6.51', 'mysql-5.7.44'], 'A standalone PARTITION BY clause');
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Partition;
    }

    /**
     * Retains the partitioning while replacing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->partitioning);
    }

    /**
     * Replaces the partitioning clause.
     */
    public function withPartitioning(TablePartitioning $partitioning): self
    {
        return $this->changed(new self($this->origin, $partitioning));
    }
}
