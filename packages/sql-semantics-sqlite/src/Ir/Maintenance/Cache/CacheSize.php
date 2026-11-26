<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Reads or sets the live cache capacity of one resolved pager.
 *
 * @visibility public
 * @example Reading the main database's live capacity
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Cache\CacheSize())->toString() // => 'PRAGMA "main".cache_size'
 */
final class CacheSize implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the resolved database and the optional live capacity request.
     */
    public function __construct(
        public readonly Identifier $schema = new Identifier('main'),
        public readonly ?CacheAmount $amount = null,
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
     * Emits the exact signed request or a native read.
     */
    public function toString(): string
    {
        $argument = $this->amount === null ? '' : '(' . $this->amount->value . ')';

        return 'PRAGMA ' . $this->schema->quoted() . '.cache_size' . $argument;
    }
}
