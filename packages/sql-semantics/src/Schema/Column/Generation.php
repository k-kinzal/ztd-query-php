<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

/**
 * Describes the source of a column value without evaluating it.
 *
 * @visibility public
 * @example Inspecting the value source of a column
 *     $column = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER DEFAULT 1)')->tables[0]->columns[0];
 *     $column->generation instanceof \SqlSemantics\Schema\Column\Generation // => true
 *     count($column->generation->expressions()) // => 1
 */
interface Generation
{
    /**
     * @return list<\SqlSemantics\Model\Expression>
     */
    public function expressions(): array;
}
