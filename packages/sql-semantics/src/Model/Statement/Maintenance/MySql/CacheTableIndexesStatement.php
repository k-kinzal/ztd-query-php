<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\MySql;

use Override;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Maintenance\IndexCache\CacheName;
use SqlSemantics\Model\Maintenance\IndexCache\DefaultCache;
use SqlSemantics\Model\Maintenance\IndexCache\TableIndexes;
use SqlSemantics\Model\Maintenance\MySql\ResultColumns;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Assigns index storage to the required key cache for one or more whole tables.
 * @visibility public
 * @example Inspecting the operation without changing server memory
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $request = (new \SqlSemantics\Binder($schema))->bind('CACHE INDEX t IN DEFAULT');
 *     $request instanceof \SqlSemantics\Model\Statement\Maintenance\MySql\CacheTableIndexesStatement // => true
 * @example Rejecting a missing table request
 *     $origin = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('SELECT 1')->origin;
 *     new \SqlSemantics\Model\Statement\Maintenance\MySql\CacheTableIndexesStatement($origin, [], \SqlSemantics\Model\Maintenance\IndexCache\DefaultCache::Instance); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class CacheTableIndexesStatement extends BoundStatement implements ResultStatement
{
    /**
     * @param non-empty-list<TableIndexes> $targets Ordered table requests
     * @throws InvalidStructure
     */
    public function __construct(
        Origin $origin,
        public readonly array $targets,
        public readonly DefaultCache|CacheName $cache,
    ) {
        if ($origin->dialect !== Dialect::MySql) {
            throw new InvalidStructure('Index-cache operations require MySQL.');
        }
        Collections::objects(Collections::nonEmpty($targets), TableIndexes::class);
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
        return new self($origin, $this->targets, $this->cache);
    }

    /**
     * Replaces the targets in an independently validated request.
     * @param non-empty-list<TableIndexes> $targets Ordered replacements
     */
    public function withTargets(array $targets): self
    {
        return $this->changed(new self($this->origin, $targets, $this->cache));
    }

    /**
     * Replaces the cache in an independently validated request.
     */
    public function withCache(DefaultCache|CacheName $cache): self
    {
        return $this->changed(new self($this->origin, $this->targets, $cache));
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
