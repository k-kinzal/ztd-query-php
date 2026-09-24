<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

/**
 * One declared array dimension, whose bound is not a runtime size guarantee.
 * @visibility public
 * @example Reading declared array bounds
 *     $identity = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER[3][])')->tables[0]->columns[0]->type->identity;
 *     $identity->dimensions[0]->length->spelling // => '3'
 *     $identity->dimensions[1]->length // => null
 */
final class ArrayDimension
{
    /**

     */
    public function __construct(
        public readonly ?Numeric\NumericParameter $length = null,
    ) {
    }

}
