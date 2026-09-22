<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Index;

use Override;

/**
 * A computed index key with a mandatory value expression.
 *
 * @visibility public
  * @example Inspecting ExpressionKey
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE INDEX ix ON t((id+1))');
 *     $key = $schema->tables[0]->indexes[0]->elements[0];
 *     $key instanceof \SqlSemantics\Schema\Index\ExpressionKey // => true
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

    /**
     * Returns the required computed expression indexed by this key.
     */
    #[Override]
    public function value(): \SqlSemantics\Model\Expression
    {
        return $this->expression;
    }
}
