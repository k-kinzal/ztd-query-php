<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A rebuild of eligible application indexes in the selected database.
 *
 * @visibility public
 * @example Selecting the explicitly identified local database
 *     $name = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('billing');
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexDatabase($name))->toString() // => 'REINDEX DATABASE "billing"'
 */
final class ReindexDatabase implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the exact database, requested mode and native profile.
     */
    public function __construct(
        public readonly Identifier $database,
        public readonly IndexRebuildMode $mode = IndexRebuildMode::Standard,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
    }

    /**
     * Identifies the native PostgreSQL profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits the resolved database and the requested application-index mode.
     */
    public function toString(): string
    {
        return 'REINDEX DATABASE ' . ($this->mode === IndexRebuildMode::Concurrent ? 'CONCURRENTLY ' : '') . $this->database->quoted();
    }
}
