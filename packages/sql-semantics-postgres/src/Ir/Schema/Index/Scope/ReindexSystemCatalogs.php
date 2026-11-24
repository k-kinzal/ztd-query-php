<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Placement\IndexTablespace;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A standard request for the pinned catalog indexes of the selected database.
 *
 * @visibility public
 * @example Selecting local system catalogs
 *     $name = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('billing');
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Scope\ReindexSystemCatalogs($name))->toString() // => 'REINDEX SYSTEM "billing"'
 */
final class ReindexSystemCatalogs implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the exact database and native grammar profile.
     */
    public function __construct(
        public readonly Identifier $database,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
        public readonly bool $verbose = false,
        public readonly ?IndexTablespace $tablespace = null,
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
     * Emits the local system catalog rebuild.
     */
    public function toString(): string
    {
        return ($this->tablespace === null ? 'REINDEX ' . ($this->verbose ? '(VERBOSE) ' : '') : $this->tablespace->prefix($this->verbose)) . 'SYSTEM ' . $this->database->quoted();
    }
}
