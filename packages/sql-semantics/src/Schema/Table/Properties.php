<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Table;

/**
 * Classified dialect-specific table properties.
 *
 * @visibility public
 * @example Identifying the dialect that owns table options
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::Sqlite))->build('CREATE TABLE t(id INTEGER) STRICT')->tables[0];
 *     $table->properties instanceof \SqlSemantics\Schema\Table\Properties // => true
 *     $table->properties->dialect() // => \SqlSemantics\Dialect::Sqlite
 */
interface Properties
{
    /**
     * Identifies the language that owns these table options.
     */
    public function dialect(): \SqlSemantics\Dialect;
}
