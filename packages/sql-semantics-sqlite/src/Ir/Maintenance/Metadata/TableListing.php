<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Native SQLite relation metadata enumeration with an optional schema and name filter.
 *
 * @visibility public
 * @example Listing tables and views in the main schema
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Maintenance\Metadata\TableListing(new \SqlSemantics\Platform\Sqlite\Ir\Identifier('main')))->toString() // => 'PRAGMA "main".table_list'
 */
final class TableListing implements SemanticsIR
{
    use Snapshot;

    /**
     * Fixes the exact native scope and optional row-name filter.
     */
    public function __construct(public readonly ?Identifier $schema = null, public readonly ?Identifier $nameFilter = null)
    {
    }

    /**
     * Identifies the pinned SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the native listing, including a filter that contains an empty name.
     */
    public function toString(): string
    {
        $qualifier = $this->schema === null ? '' : $this->schema->quoted() . '.';
        $argument = $this->nameFilter === null ? '' : '(' . $this->nameFilter->quoted() . ')';

        return 'PRAGMA ' . $qualifier . 'table_list' . $argument;
    }
}
