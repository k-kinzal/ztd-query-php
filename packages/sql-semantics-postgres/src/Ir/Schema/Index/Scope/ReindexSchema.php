<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\IndexRebuildMode;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A rebuild request for eligible indexes in one persistent application namespace.
 *
 * @visibility public
 * @example Rebuilding the application schema
 *     $schema = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('reporting');
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexSchema($schema))->toString() // => 'REINDEX SCHEMA "reporting"'
 */
final class ReindexSchema implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the selected native schema, concurrency mode and grammar profile.
     */
    public function __construct(
        public readonly Identifier $schema,
        public readonly IndexRebuildMode $mode = IndexRebuildMode::Standard,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        Check::input(
            $schema->value !== 'pg_temp' && !IndexSchemas::system($schema->value) && !IndexSchemas::sessionInternal($schema->value),
            'A schema rebuild requires a persistent application namespace.',
        );
    }

    /**
     * Identifies the fixed native PostgreSQL profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits the namespace selector and requested concurrency mode.
     */
    public function toString(): string
    {
        return 'REINDEX SCHEMA ' . ($this->mode === IndexRebuildMode::Concurrent ? 'CONCURRENTLY ' : '') . $this->schema->quoted();
    }
}
