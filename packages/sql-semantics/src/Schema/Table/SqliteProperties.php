<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Table;

use Override;

/**
 * SQLite table storage and typing policies.
 *
 * @visibility public
 */
final class SqliteProperties implements Properties
{
    /**
     * Constructs a valid declaration.

     */
    public function __construct(
        public readonly bool $withoutRowId = false,
        public readonly bool $strict = false,
        public readonly bool $temporary = false,
    ) {
    }
    /**
     * Returns the SQL dialect that defines these options.
     */
    #[Override]
    public function dialect(): \SqlSemantics\Dialect
    {
        return \SqlSemantics\Dialect::Sqlite;
    }
}
