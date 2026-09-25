<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Operator\Qualified;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A PostgreSQL binary operation through an explicitly named operator: left OPERATOR(schema.symbol) right.
 * Its result type is unknown because the operator is not resolved against a catalog.
 * @visibility public
 * @example Reading the operator and its operands
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT 1 OPERATOR(geo.<->) 2');
 *     $operation = $query->outputs[0]->expression;
 *     [$operation->operator->qualifier, $operation->operator->symbol, $operation->left->spelling(), $operation->type->name] // => [['geo'], '<->', '1', 'unknown']
 *     (new \SqlSemantics\SimpleSerializer())->serialize($query) // => 'SELECT (1 OPERATOR("geo".<->) 2)'
 */
final class QualifiedInfixOperation extends Expression
{
    /**
     * Requires PostgreSQL facts and operands.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        Node|Token $source,
        public readonly QualifiedOperator $operator,
        public readonly Expression $left,
        public readonly Expression $right,
    ) {
        foreach ([$facts->type, $left->type, $right->type] as $type) {
            if ($type->dialect !== Dialect::PostgreSql) {
                throw new InvalidStructure('A named operator operation requires PostgreSQL operands.');
            }
        }
        parent::__construct($facts, $source);
    }

    /**
     * Identifies an operator application.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Operator;
    }

    /**
     * @return list<Expression> The left operand followed by the right operand
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->left, $this->right];
    }

    /**
     * Returns OPERATOR(path.symbol).
     */
    #[Override]
    public function spelling(): string
    {
        return $this->operator->spelling();
    }

    /**
     * Keeps the operator and operands with new facts.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->operator, $this->left, $this->right);
    }
}
