<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Function;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * Invokes a named aggregate once per input row, without a value argument.
 * @visibility public
  * @example Inspecting AllRowsAggregate
 *     $integer = new \SqlSemantics\Type\TypeDescriptor(\SqlSemantics\Dialect::PostgreSql, \SqlSemantics\Type\Identity\BuiltinIdentity::Integer);
 *     $signature = new \SqlSemantics\Schema\FunctionSignature('row_total', [], $integer, \SqlSemantics\Type\Nullability::NotNull, aggregate: true, schema: 'app');
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()->withFunctions($signature);
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('SELECT app.row_total(*) FILTER (WHERE TRUE)');
 *     $aggregate = $statement->outputs[0]->expression;
 *     $aggregate instanceof \SqlSemantics\Model\Scalar\Function\AllRowsAggregate // => true
 */
final class AllRowsAggregate extends Expression
{
    /**
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        public readonly FunctionReference $function,
        public readonly ?Expression $filter,
    ) {
        parent::__construct($facts, $source);
        foreach ($this->inputs() as $input) {
            if ($input->type->dialect !== $facts->type->dialect) {
                throw new \SqlSemantics\Model\Validation\InvalidStructure('Expression operands cannot mix SQL dialects.');
            }
        }
    }

    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Aggregate;
    }

    /**

     * @return list<Expression>

     */
    #[Override]
    public function inputs(): array
    {
        return $this->filter === null ? [] : [$this->filter];
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function spelling(): string
    {
        return strtoupper(implode('.', $this->function->name()->parts));
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->function, $this->filter);
    }

}
