<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Table;

use Override;

/**
 * SQLite table storage and typing policies.
 *
 * @visibility public
 * @example Reading SQLite table policies
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TEMP TABLE t(id INTEGER PRIMARY KEY) WITHOUT ROWID, STRICT')->tables[0];
 *     $table->properties instanceof \SqlSemantics\Schema\Table\SqliteProperties // => true
 *     $table->properties->withoutRowId // => true
 *     $table->properties->strict // => true
 *     $table->properties->temporary // => true
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
