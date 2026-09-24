<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Temporal;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A PostgreSQL (start, end) OVERLAPS (start, end) test of two time periods; each end may also be an interval length.
 * @visibility public
 * @example Reading the two periods
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("SELECT (DATE '2001-01-01', DATE '2001-02-01') OVERLAPS (DATE '2001-01-15', INTERVAL '1 day')");
 *     $overlap = $statement->outputs[0]->expression;
 *     $overlap->leftStart->type->name // => 'date'
 *     $overlap->rightEnd->type->name // => 'interval'
 */
final class PeriodOverlap extends Expression
{
    /**
     * Derives a nullable boolean from four PostgreSQL period bounds.
     * @throws InvalidStructure
     */
    public function __construct(
        Node|Token $source,
        public readonly Expression $leftStart,
        public readonly Expression $leftEnd,
        public readonly Expression $rightStart,
        public readonly Expression $rightEnd,
    ) {
        foreach ([$leftStart, $leftEnd, $rightStart, $rightEnd] as $bound) {
            if ($bound->type->dialect !== Dialect::PostgreSql) {
                throw new InvalidStructure('OVERLAPS requires PostgreSQL period bounds.');
            }
        }
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, 'boolean'), Nullability::MaybeNull), $source);
    }

    /**
     * Identifies a predicate operator.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Operator;
    }

    /**
     * @return list<Expression> The left period bounds followed by the right period bounds
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->leftStart, $this->leftEnd, $this->rightStart, $this->rightEnd];
    }

    /**
     * Returns the fixed operator keyword.
     */
    #[Override]
    public function spelling(): string
    {
        return 'OVERLAPS';
    }

    /**
     * Preserves the fixed predicate facts.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('OVERLAPS facts are fixed.');
        }
        return new static($this->source, $this->leftStart, $this->leftEnd, $this->rightStart, $this->rightEnd);
    }
}
