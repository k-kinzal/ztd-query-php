<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\MySql;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Maintenance\IndexCache\AllPartitions;
use SqlSemantics\Model\Maintenance\IndexCache\CacheName;
use SqlSemantics\Model\Maintenance\IndexCache\DefaultCache;
use SqlSemantics\Model\Maintenance\IndexCache\NamedPartitions;
use SqlSemantics\Model\Maintenance\IndexCache\TableIndexes;
use SqlSemantics\Model\Maintenance\MySql\ResultColumns;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Assigns index storage to the required key cache for selected partitions of one table.
 * @visibility public
 * @example Inspecting the operation without changing server memory
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $request = (new \SqlSemantics\Binder($schema))->bind('CACHE INDEX t PARTITION (ALL) IN DEFAULT');
 *     $request instanceof \SqlSemantics\Model\Statement\Maintenance\MySql\CachePartitionIndexesStatement // => true
 */
final class CachePartitionIndexesStatement extends BoundStatement implements ResultStatement
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly TableIndexes $target,
        public readonly AllPartitions|NamedPartitions $partitions,
        public readonly DefaultCache|CacheName $cache,
    ) {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('Index-cache operations require MySQL.');
        }
        parent::__construct($origin);
    }

    #[Override]
    protected function operation(): StatementKind
    {
        return StatementKind::Cache;
    }

    /**
     * Retains the request when changing diagnostic provenance.
     */
    #[Override]
    public function withOrigin(Origin $origin): static
    {
        return new self($origin, $this->target, $this->partitions, $this->cache);
    }

    /**
     * Replaces the target in an independently validated request.
     */
    public function withTarget(TableIndexes $target): self
    {
        return $this->changed(new self($this->origin, $target, $this->partitions, $this->cache));
    }

    /**
     * Replaces the partitions in an independently validated request.
     */
    public function withPartitions(AllPartitions|NamedPartitions $partitions): self
    {
        return $this->changed(new self($this->origin, $this->target, $partitions, $this->cache));
    }

    /**
     * Replaces the cache in an independently validated request.
     */
    public function withCache(DefaultCache|CacheName $cache): self
    {
        return $this->changed(new self($this->origin, $this->target, $this->partitions, $cache));
    }

    /**
     * @return list<OutputColumn> Table, operation, message category, and message text
     */
    #[Override]
    public function resultColumns(): array
    {
        return ResultColumns::status($this->origin);
    }
}
