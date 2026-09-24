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
 * A PostgreSQL value AT TIME ZONE zone, or AT LOCAL for the session time zone, which swaps between zoned and local time.
 * @visibility public
 * @example Reading the converted value and the zone
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("SELECT LOCALTIMESTAMP AT TIME ZONE 'UTC', LOCALTIMESTAMP AT LOCAL");
 *     $statement->outputs[0]->expression->zone?->spelling() // => "'UTC'"
 *     $statement->outputs[0]->expression->type->name // => 'timestamptz'
 *     $statement->outputs[1]->expression->zone // => null
 */
final class ZoneConversion extends Expression
{
    /**
     * Derives the converted type from the value: timestamptz becomes timestamp, timestamp and date become timestamptz, times become timetz.
     * @param Expression|null $zone Time zone name or interval offset; null for AT LOCAL
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly Expression $value, public readonly ?Expression $zone)
    {
        if ($value->type->dialect !== Dialect::PostgreSql || $zone !== null && $zone->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('AT TIME ZONE and AT LOCAL require PostgreSQL operands.');
        }
        $converted = match ($value->type->name) {
            'timestamptz' => 'timestamp',
            'timestamp', 'date' => 'timestamptz',
            'time', 'timetz' => 'timetz',
            default => 'unknown',
        };
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin(Dialect::PostgreSql, $converted), Nullability::MaybeNull), $source);
    }

    /**
     * Identifies an operator expression.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Operator;
    }

    /**
     * @return list<Expression> The value followed by the zone when one is written
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->value, ...($this->zone === null ? [] : [$this->zone])];
    }

    /**
     * Returns the operator keywords.
     */
    #[Override]
    public function spelling(): string
    {
        return $this->zone === null ? 'AT LOCAL' : 'AT TIME ZONE';
    }

    /**
     * Preserves the facts derived from the value.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Time zone conversion facts are derived from its value.');
        }
        return new static($this->source, $this->value, $this->zone);
    }
}
