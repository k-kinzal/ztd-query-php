<?php

declare(strict_types=1);

namespace SqlSemantics;

/**
 * A checked result of analyzing SQL. The database implementation provides its structure and semantic facts.
 *
 * @visibility public
 * @example Retrieving SQL from any database query result
 *     $query = new class implements \SqlSemantics\Query { public function toString(): string { return 'SELECT 1'; } };
 *     $query->toString() // => 'SELECT 1'
 */
interface Query
{
    /**
     * Returns SQL rendered from the analyzed structure.
     */
    public function toString(): string;
}
