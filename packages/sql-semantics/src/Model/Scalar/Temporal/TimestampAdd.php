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
use SqlSemantics\Model\Validation\QueryComparison;

/**
 * MySQL TIMESTAMPADD(unit, quantity, value): adds a quantity of one simple unit to a temporal input without evaluating either.
 * @visibility public
 * @example Reading the unit and operands of TIMESTAMPADD
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(d DATETIME)')))->bind('SELECT TIMESTAMPADD(DAY, 1, d) FROM t');
 *     $call = $query->outputs[0]->expression;
 *     [$call->unit, $call->quantity->spelling(), $call->type->name] // => [\SqlSemantics\Model\Scalar\Temporal\MySqlUnit::Day, '1', 'datetime']
 * @example Rejecting a compound unit
 *     $value = \SqlSemantics\Model\Expression::literal(1, \SqlSemantics\Dialect::MySql);
 *     new \SqlSemantics\Model\Scalar\Temporal\TimestampAdd(new \SqlParser\Parser\Node('expr', 0, []), \SqlSemantics\Model\Scalar\Temporal\MySqlUnit::DayHour, $value, $value, \SqlSemantics\Model\Scalar\Temporal\DateArithmeticRules::Current); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class TimestampAdd extends Expression
{
    /**
     * The simple units TIMESTAMPADD and TIMESTAMPDIFF accept.
     */
    public const UNITS = [MySqlUnit::Microsecond, MySqlUnit::Second, MySqlUnit::Minute, MySqlUnit::Hour, MySqlUnit::Day, MySqlUnit::Week, MySqlUnit::Month, MySqlUnit::Quarter, MySqlUnit::Year];

    /**
     * The interval quantity, read in the unit.
     */
    public readonly Expression $quantity;
    /**
     * The date or datetime input.
     */
    public readonly Expression $value;

    /**
     * Derives the result family from the input and the unit under the selected release's arithmetic rules.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly MySqlUnit $unit, Expression $quantity, Expression $value, public readonly DateArithmeticRules $rules)
    {
        if ($quantity->type->dialect !== Dialect::MySql || $value->type->dialect !== Dialect::MySql || !in_array($unit, self::UNITS, true)) {
            throw new InvalidStructure('TIMESTAMPADD takes MySQL operands and one simple unit.');
        }
        if (!in_array(QueryComparison::width($quantity), [null, 1], true) || !in_array(QueryComparison::width($value), [null, 1], true)) {
            throw new InvalidStructure('Temporal arithmetic requires scalar operands.');
        }
        $this->quantity = DateArithmeticFacts::parameter($quantity, 'bigint', $rules);
        $this->value = DateArithmeticFacts::parameter($value, IntervalFields::calendarOnly($unit) ? 'date' : 'datetime', $rules);
        $extensions = array_values(array_unique([...$quantity->nullExtendedBy, ...$value->nullExtendedBy]));
        parent::__construct(new ExpressionFacts(DateArithmeticFacts::result($this->value, $unit, $rules), DateArithmeticFacts::nullability($this->value, $this->quantity), $extensions), $source);
    }

    /**
     * Identifies TIMESTAMPADD.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::TimestampAdd;
    }

    /**
     * @return list<Expression> The quantity and the temporal input, in argument order
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->quantity, $this->value];
    }

    /**
     * Returns the function name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'TIMESTAMPADD';
    }

    /**
     * Keeps the facts derived from the unit and operands.
     * @visibility SqlSemantics
     * @throws InvalidStructure
     */
    #[Override]
    public function withFacts(ExpressionFacts $facts): static
    {
        if ($facts !== $this->facts) {
            throw new InvalidStructure('TIMESTAMPADD facts are derived from its unit and operands.');
        }
        return new self($this->source, $this->unit, $this->quantity, $this->value, $this->rules);
    }
}
