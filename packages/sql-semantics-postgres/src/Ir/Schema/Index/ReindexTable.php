<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index;

use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A rebuild request for the index set of one resolved PostgreSQL relation.
 *
 * @visibility public
 * @example Rebuilding the indexes of a local table
 *     $identifier = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     $target = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTableTarget(new $identifier('app'), new $identifier('orders'));
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexTable($target))->toString() // => 'REINDEX TABLE "app"."orders"'
 */
final class ReindexTable implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the index owner, concurrency request and PostgreSQL grammar profile.
     */
    public function __construct(
        public readonly ReindexTableTarget $target,
        public readonly IndexRebuildMode $mode = IndexRebuildMode::Standard,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
    }

    /**
     * Identifies the fixed native PostgreSQL profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits the owner-wide selector and requested concurrency precondition.
     */
    public function toString(): string
    {
        return 'REINDEX TABLE ' . ($this->mode === IndexRebuildMode::Concurrent ? 'CONCURRENTLY ' : '') . $this->target->quoted();
    }
}
