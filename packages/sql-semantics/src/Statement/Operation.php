<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

/**
 * An immutable operation expressed by SQL, independent of its parser grammar.
 * @example Describing the operation
 *     $operation = new \SqlSemantics\Statement\Transaction\Rollback();
 *     $operation instanceof \SqlSemantics\Statement\Operation // => true
 * @visibility public
 */
interface Operation
{
    /**
     * Reconstructs the operation from its meaning and declared values.
     */
    public function toString(): string;
}
