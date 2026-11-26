<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Model\Statement\Type;

/**
 * A data type of one database: the declared type of a column or the type of an expression.
 *
 * Each database package provides its closed set of descriptor classes.
 *
 * @visibility SqlSemantics
 */
interface TypeDescriptor
{
    /**
     * Names the type as the database reports it.
     */
    public function name(): string;
}
