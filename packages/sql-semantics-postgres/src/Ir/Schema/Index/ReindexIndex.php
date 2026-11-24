<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index;

use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A rebuild request for one resolved PostgreSQL index.
 *
 * @visibility public
 * @example Rebuilding a named application's index
 *     $name = \SqlSemantics\Platform\PostgreSql\Ir\Identifier::class;
 *     $index = new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexReference(new $name('app'), new $name('selected'), new $name('orders'));
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\ReindexIndex($index))->toString() // => 'REINDEX INDEX "app"."selected"'
 */
final class ReindexIndex implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the exact index, rebuild mode and PostgreSQL grammar profile.
     */
    public function __construct(
        public readonly IndexReference $target,
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
     * Emits the qualified index and the requested concurrency precondition.
     */
    public function toString(): string
    {
        return 'REINDEX INDEX ' . ($this->mode === IndexRebuildMode::Concurrent ? 'CONCURRENTLY ' : '') . $this->target->quoted();
    }
}
