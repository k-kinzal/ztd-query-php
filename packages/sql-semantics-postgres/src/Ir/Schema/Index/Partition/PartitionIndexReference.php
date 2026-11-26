<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A resolved named index on a persistent partition branch or leaf.
 *
 * @visibility public
 * @example Selecting a named index and fixing its owning table
 *     $id = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     $branch = \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\PartitionRebuildKind::Branch;
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\PartitionIndexReference(new $id('app'), new $id('lookup'), new $id('orders'), $branch))->quoted() // => '"app"."lookup"'
 */
final class PartitionIndexReference
{
    use Snapshot;

    /**
     * Fixes the persistent index identity and whether it selects physical descendants.
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly Identifier $index,
        public readonly Identifier $table,
        public readonly PartitionRebuildKind $kind,
    ) {
        Check::input(
            $schema->value !== 'pg_temp' && !IndexSchemas::system($schema->value) && !IndexSchemas::sessionInternal($schema->value),
            'A partition index requires a persistent application schema.',
        );
        Check::input($index->value !== $table->value, 'A partition index and its table require distinct relation names.');
    }

    /**
     * Emits the fixed index coordinate.
     */
    public function quoted(): string
    {
        return $this->schema->quoted() . '.' . $this->index->quoted();
    }
}
