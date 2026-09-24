<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

/**
 * The classified type identity carried by a semantic expression or column.
 * @visibility public
 * @example Reading the canonical name of a column type
 *     $type = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(a BIGINT UNSIGNED)')->tables[0]->columns[0]->type;
 *     $type->identity instanceof \SqlSemantics\Type\Identity\TypeIdentity // => true
 *     $type->identity->name() // => 'bigint unsigned'
 */
interface TypeIdentity
{
    /**
     * Returns the canonical type name used in diagnostics.
     */
    public function name(): string;
}
