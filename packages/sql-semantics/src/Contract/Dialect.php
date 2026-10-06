<?php

declare(strict_types=1);

namespace SqlSemantics\Contract;

/**
 * The database whose SQL is analyzed; each database package provides one enum that implements it.
 *
 * @visibility public
 * @example Selecting a database
 *     \SqlSemantics\Platform\Sqlite\Dialect::Sqlite->database() // => 'sqlite'
 */
interface Dialect
{
    /**
     * Names the database family as the grammar releases do.
     */
    public function database(): string;
}
