<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

use Override;

/**
 * A PostgreSQL integer column declared smallserial, serial or bigserial: its default draws the next value of the
 * sequence the declaration creates and owns, and the declaration makes it NOT NULL. The column's type is the
 * smallint, integer or bigint the serial type stands for.
 *
 * @visibility public
 * @example Inspecting a serial column
 *     $column = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id bigserial)')->tables[0]->columns[0];
 *     $column->generation instanceof \SqlSemantics\Schema\Column\SerialColumn // => true
 *     $column->type->name // => 'bigint'
 *     $column->nullability // => \SqlSemantics\Type\Nullability::NotNull
 */
final class SerialColumn implements Generation
{
    /**
     * Returns no generation expression: the owned sequence supplies the default.
     */
    #[Override]
    public function expressions(): array
    {
        return [];
    }
}
