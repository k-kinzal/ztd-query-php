<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Conditional;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * PostgreSQL `value IS [NOT] JSON [VALUE | ARRAY | OBJECT | SCALAR] [WITH | WITHOUT UNIQUE [KEYS]]`: whether a text or binary value parses as the required kind of JSON item.
 * Binding does not parse the value.
 *
 * @visibility public
 * @example Reading the JSON predicate
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("SELECT '{}' IS NOT JSON OBJECT WITH UNIQUE KEYS");
 *     $predicate = $statement->outputs[0]->expression;
 *     [$predicate->negated, $predicate->itemKind->value, $predicate->uniqueKeys] // => [true, 'OBJECT', true]
 */
final class JsonPredicate extends Expression
{
    /**
     * @param bool $negated Whether IS NOT JSON inverts the test
     * @param bool $uniqueKeys Whether WITH UNIQUE KEYS also rejects objects that repeat a key
     * @throws InvalidStructure
     */
    public function __construct(ExpressionFacts $facts, Node|Token $source, public readonly Expression $operand, public readonly JsonItemKind $itemKind = JsonItemKind::Value, public readonly bool $negated = false, public readonly bool $uniqueKeys = false)
    {
        if ($facts->type->dialect !== Dialect::PostgreSql || $operand->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('IS JSON is a PostgreSQL predicate.');
        }
        parent::__construct($facts, $source);
    }

    /**
     * Returns the operator category.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Operator;
    }

    /**
     * @return list<Expression>
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->operand];
    }

    /**
     * Returns the predicate spelling for diagnostics.
     */
    #[Override]
    public function spelling(): string
    {
        return $this->negated ? 'IS NOT JSON' : 'IS JSON';
    }

    /**
     * Replaces derived type facts while retaining the test.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        return new static($facts, $this->source, $this->operand, $this->itemKind, $this->negated, $this->uniqueKeys);
    }
}
