<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Scalar\Operator;

use Override;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\Identity\BuiltinIdentity;
use SqlSemantics\Type\Identity\TemporalStorage;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A MySQL `CAST(timestamp AT TIME ZONE [INTERVAL] 'zone' AS DATETIME[(n)])` conversion to a zone's wall-clock time.
 * @visibility public
 * @example Reading the zone and the result precision
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t (at TIMESTAMP)');
 *     $query = (new \SqlSemantics\Binder($schema))->bind("SELECT CAST(at AT TIME ZONE '+00:00' AS DATETIME(3)) FROM t");
 *     $cast = $query->outputs[0]->expression;
 *     $cast->zone->text // => "'+00:00'"
 *     $cast->interval // => false
 *     $cast->type->name // => 'datetime'
 */
final class TimeZoneCast extends Expression
{
    /**
     * Derives a DATETIME result with the requested fractional-second precision.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly Expression $operand, public readonly Literal $zone, public readonly bool $interval, public readonly ?int $precision)
    {
        if ($operand->type->dialect !== Dialect::MySql || $zone->type->dialect !== Dialect::MySql) {
            throw new InvalidStructure('AT TIME ZONE casts require MySQL.');
        }
        if ($zone->literalKind !== LiteralKind::Text) {
            throw new InvalidStructure('A time zone is written as a string literal.');
        }
        if ($precision !== null && ($precision < 0 || $precision > 6)) {
            throw new InvalidStructure('DATETIME precision is between 0 and 6.');
        }
        $type = new TypeDescriptor(Dialect::MySql, new TemporalStorage(BuiltinIdentity::Datetime, $precision === null ? null : new \SqlSemantics\Type\Identity\Numeric\NumericParameter((string) $precision)));
        parent::__construct(new ExpressionFacts($type, $operand->nullability, $operand->nullExtendedBy), $source);
    }

    /**
     * Identifies a conversion.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::Cast;
    }

    /**
     * @return list<Expression> The converted value followed by the zone
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->operand, $this->zone];
    }

    /**
     * Returns the conversion name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'CAST';
    }

    /**
     * Preserves the facts derived from the operand and precision.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('Time zone cast facts are derived from its operands.');
        }
        return new static($this->source, $this->operand, $this->zone, $this->interval, $this->precision);
    }
}
