<?php

declare(strict_types=1);

namespace SqlSemantics\Schema;

use SqlParser\Parser\Node;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;

/**
 * An ordered index key; column prefixes and computed keys are distinct forms.
 *
 * @visibility public
 * @example Reading the indexed value of a computed key
 *     $key = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE INDEX ix ON t((id + 1) ASC)')->tables[0]->indexes[0]->elements[0];
 *     $key->value()->structure()->toString() // => '("id" + 1)'
 *     $key->direction // => \SqlSemantics\Schema\Index\Direction::Ascending
 */
abstract class IndexElement
{
    /**
     * @param list<Storage\Parameter> $operatorParameters
     */
    public function __construct(
        public readonly ?Index\Direction $direction,
        public readonly ?Index\NullOrder $nulls,
        public readonly ?QualifiedName $collation,
        public readonly ?QualifiedName $operatorClass,
        public readonly array $operatorParameters,
        public readonly Node $source,
    ) {
        \SqlSemantics\Model\Validation\Collections::objects($operatorParameters, Storage\Parameter::class);
    }

    /**
     * Returns the value indexed for each row.
     */
    abstract public function value(): Expression;
}
