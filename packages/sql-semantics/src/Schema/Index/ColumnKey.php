<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Index;

use Override;

/**
 * A column reference with an optional indexed prefix.
 *
 * @visibility public
 */
final class ColumnKey extends \SqlSemantics\Schema\IndexElement
{
    /**
     * Constructs a valid declaration.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        public readonly \SqlSemantics\Model\Scalar\Reference\ColumnReference|\SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference $column,
        public readonly ?int $prefixLength = null,
        ?Direction $direction = null,
        ?NullOrder $nulls = null,
        ?\SqlSemantics\Model\Relation\QualifiedName $collation = null,
        ?\SqlSemantics\Model\Relation\QualifiedName $operatorClass = null,
        array $operatorParameters = [],
        \SqlParser\Parser\Node $source = new \SqlParser\Parser\Node('index_key', 0, []),
    ) {
        if ($prefixLength !== null && $prefixLength <= 0) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('An index prefix requires a positive length.');
        }
        parent::__construct($direction, $nulls, $collation, $operatorClass, $operatorParameters, $source);
    }

    /**
     * Returns the required column expression indexed by this key.
     */
    #[Override]
    public function value(): \SqlSemantics\Model\Expression
    {
        return $this->column;
    }
}
