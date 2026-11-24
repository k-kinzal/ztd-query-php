<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Reads or sets the persistent cache default of one resolved database.
 *
 * @visibility public
 * @example Reading the main cache cookie
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\DefaultCacheSize())->toString() // => 'PRAGMA "main".default_cache_size'
 */
final class DefaultCacheSize implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the database and its optional persistent default request.
     */
    public function __construct(
        public readonly Identifier $schema = new Identifier('main'),
        public readonly ?DefaultCachePages $capacity = null,
    ) {
    }

    /**
     * Identifies the pinned SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits a persistent read or the exact cookie request.
     */
    public function toString(): string
    {
        $argument = $this->capacity === null ? '' : '(' . $this->capacity->pages . ')';

        return 'PRAGMA ' . $this->schema->quoted() . '.default_cache_size' . $argument;
    }
}
