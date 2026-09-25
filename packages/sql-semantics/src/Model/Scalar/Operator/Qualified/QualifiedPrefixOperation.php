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
 * A PostgreSQL prefix operation through an explicitly named operator: OPERATOR(schema.symbol) operand.
 * Its result type is unknown because the operator is not resolved against a catalog.
 * @visibility public
 * @example Reading the operator and its operand
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build();
 *     $query = (new \SqlSemantics\Binder($schema))->bind('SELECT OPERATOR(geo.@@) 2');
 *     $operation = $query->outputs[0]->expression;
 *     [$operation->operator->qualifier, $operation->operator->symbol, $operation->operand->spelling(), $operation->type->name] // => [['geo'], '@@', '2', 'unknown']
 *     (new \SqlSemantics\SimpleSerializer())->serialize($query) // => 'SELECT (OPERATOR("geo".@@) 2)'
 */
final class QualifiedPrefixOperation extends Expression
{
    /**
     * Requires PostgreSQL facts and a PostgreSQL operand.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    public function __construct(
        ExpressionFacts $facts,
        Node|Token $source,
        public readonly QualifiedOperator $operator,
        public readonly Expression $operand,
    ) {
        foreach ([$facts->type, $operand->type] as $type) {
            if ($type->dialect !== Dialect::PostgreSql) {
                throw new InvalidStructure('A named operator operation requires a PostgreSQL operand.');
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
     * @return list<Expression> The single operand
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->operand];
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
     * Keeps the operator and operand with new facts.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->operator, $this->operand);
    }
}
