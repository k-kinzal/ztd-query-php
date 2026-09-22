<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Query;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * ScalarSubquery has explicit semantic operands and a fixed expression category.
 * @visibility public
  * @example Inspecting ScalarSubquery
 *     $binder = new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build());
 *     $query = $binder->bind('SELECT (SELECT ROW(1,2))=ROW(1,2)');
 *     $query->outputs[0]->expression->left instanceof \SqlSemantics\Model\Scalar\Query\ScalarSubquery // => true
 */
final class ScalarSubquery extends Expression
{
    /**
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        \SqlParser\Parser\Node|\SqlParser\Lexer\Token $source,
        public readonly \SqlSemantics\Model\BoundQuery $query,
    ) {
        $width = \SqlSemantics\Model\Validation\RowShape::width($query);
        if ($width !== null && $width !== 1) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A scalar subquery requires exactly one result column.');
        }
        if ($query->origin->dialect !== $facts->type->dialect) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A scalar subquery must use its query dialect.');
        }
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
        return ExpressionKind::Subquery;
    }

    /**

     * @return list<Expression>

     */
    #[Override]
    public function inputs(): array
    {
        return array_map(static fn ($output): Expression => $output->expression, $this->query->resultColumns());
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function spelling(): string
    {
        return 'SCALAR';
    }

    /**

     * @visibility SqlSemantics

     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->query);
    }

    /**
     * Returns the required nested query that supplies this expression.
     */
    #[Override]
    public function subquery(): \SqlSemantics\Model\BoundQuery
    {
        return $this->query;
    }
}
