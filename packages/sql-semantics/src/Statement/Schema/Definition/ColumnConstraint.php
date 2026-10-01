<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema\Definition;

/**
 * A typed rule attached to a declared column, independent of parser productions.
 * @visibility public
 * @example Declaring a rule on column contents
 *     new \SqlSemantics\Statement\Schema\Definition\ColumnNullability(false) instanceof \SqlSemantics\Statement\Schema\Definition\ColumnConstraint // => true
 */
interface ColumnConstraint
{
    /**
     * Reconstructs the rule from its semantic arguments.
     */
    public function toString(): string;
}
