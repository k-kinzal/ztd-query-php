<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Type;

/**
 * A data type of one database: the declared type of a column or the type of an expression.
 *
 * Each database package provides its closed set of descriptor classes.
 *
 * @visibility public
 * @example Reading the type of a declared column
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER)')->declarations()[0];
 *     $table->columns[0]->type->name() // => 'INTEGER'
 */
interface TypeDescriptor
{
    /**
     * Names the type as the database reports it.
     */
    public function name(): string;
}
