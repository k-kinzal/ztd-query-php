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
