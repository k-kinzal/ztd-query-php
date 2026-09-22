<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Index;

use Override;

/**
 * A computed index key with a mandatory value expression.
 *
 * @visibility public
 */
final class ExpressionKey extends \SqlSemantics\Schema\IndexElement
{
    /**
     * Constructs a valid declaration.

     */
    public function __construct(
        public readonly \SqlSemantics\Model\Expression $expression,
        ?Direction $direction = null,
        ?NullOrder $nulls = null,
        ?\SqlSemantics\Model\Relation\QualifiedName $collation = null,
        ?\SqlSemantics\Model\Relation\QualifiedName $operatorClass = null,
        array $operatorParameters = [],
        \SqlParser\Parser\Node $source = new \SqlParser\Parser\Node('index_key', 0, []),
    ) {
        parent::__construct($direction, $nulls, $collation, $operatorClass, $operatorParameters, $source);
    }

    #[Override]
    public function value(): \SqlSemantics\Model\Expression
    {
        return $this->expression;
    }
}
