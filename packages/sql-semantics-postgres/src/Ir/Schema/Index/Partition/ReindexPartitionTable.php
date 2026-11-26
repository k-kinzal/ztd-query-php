<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Placement\IndexTablespace;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A rebuild on a resolved persistent partitioned branch or attached leaf.
 *
 * @visibility public
 * @example Rebuilding one selected subtree
 *     $identifier = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     $target = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName(new $identifier('app'), new $identifier('orders'));
 *     $branch = \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\PartitionRebuildKind::Branch;
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Partition\ReindexPartitionTable($target, $branch))->toString() // => 'REINDEX TABLE "app"."orders"'
 */
final class ReindexPartitionTable implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the declared table and partition role under the given native profile.
     */
    public function __construct(
        public readonly TableName $target,
        public readonly PartitionRebuildKind $kind,
        public readonly IndexRebuildMode $mode = IndexRebuildMode::Standard,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
        public readonly bool $verbose = false,
        public readonly ?IndexTablespace $tablespace = null,
    ) {
        Check::input(!IndexSchemas::system($target->schema->value), 'A partition rebuild requires an application schema.');
    }

    /**
     * Identifies the fixed native PostgreSQL profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits the selected partition relation and requested concurrency policy.
     */
    public function toString(): string
    {
        return ($this->tablespace === null ? 'REINDEX ' . ($this->verbose ? '(VERBOSE) ' : '') : $this->tablespace->prefix($this->verbose)) . 'TABLE ' . ($this->mode === IndexRebuildMode::Concurrent ? 'CONCURRENTLY ' : '') . $this->target->quoted();
    }
}
