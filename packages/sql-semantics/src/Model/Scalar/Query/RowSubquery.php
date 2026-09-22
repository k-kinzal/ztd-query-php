<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Query;

use Override;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;

/**
 * RowSubquery has explicit semantic operands and a fixed expression category.
 * @visibility public
 */
final class RowSubquery extends Expression
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
        if ($width !== null && $width < 2) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A row subquery requires multiple result columns.');
        }
        if ($query->origin->dialect !== $facts->type->dialect || $facts->type->identity !== \SqlSemantics\Type\Identity\BuiltinIdentity::Record) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A row subquery has record type in the query dialect.');
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
        return ExpressionKind::RowSubquery;
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
        return 'ROW';
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
