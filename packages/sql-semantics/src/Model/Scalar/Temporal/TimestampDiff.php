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
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * MySQL TIMESTAMPDIFF(unit, start, end): the whole number of units from start to end, without evaluating either.
 * @visibility public
 * @example Reading the unit and operands of TIMESTAMPDIFF
 *     $query = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(a DATE, b DATE)')))->bind('SELECT TIMESTAMPDIFF(MONTH, a, b) FROM t');
 *     $call = $query->outputs[0]->expression;
 *     [$call->unit, $call->type->name] // => [\SqlSemantics\Model\Scalar\Temporal\MySqlUnit::Month, 'bigint']
 * @example Rejecting a compound unit
 *     $value = \SqlSemantics\Model\Expression::literal(1, \SqlSemantics\Dialect::MySql);
 *     new \SqlSemantics\Model\Scalar\Temporal\TimestampDiff(new \SqlParser\Parser\Node('expr', 0, []), \SqlSemantics\Model\Scalar\Temporal\MySqlUnit::YearMonth, $value, $value); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class TimestampDiff extends Expression
{
    /**
     * Derives an integer result that is NULL when either input is NULL or not a valid temporal value.
     * @throws InvalidStructure
     */
    public function __construct(Node|Token $source, public readonly MySqlUnit $unit, public readonly Expression $start, public readonly Expression $end)
    {
        if ($start->type->dialect !== Dialect::MySql || $end->type->dialect !== Dialect::MySql || !in_array($unit, TimestampAdd::UNITS, true)) {
            throw new InvalidStructure('TIMESTAMPDIFF takes MySQL operands and one simple unit.');
        }
        if (!in_array(QueryComparison::width($start), [null, 1], true) || !in_array(QueryComparison::width($end), [null, 1], true)) {
            throw new InvalidStructure('Temporal arithmetic requires scalar operands.');
        }
        $nullability = $start->nullability === Nullability::AlwaysNull || $end->nullability === Nullability::AlwaysNull ? Nullability::AlwaysNull : Nullability::MaybeNull;
        parent::__construct(new ExpressionFacts(TypeDescriptor::builtin(Dialect::MySql, 'bigint'), $nullability, array_values(array_unique([...$start->nullExtendedBy, ...$end->nullExtendedBy]))), $source);
    }

    /**
     * Identifies TIMESTAMPDIFF.
     */
    #[Override]
    protected function operation(): ExpressionKind
    {
        return ExpressionKind::TimestampDiff;
    }

    /**
     * @return list<Expression> The start and end inputs, in argument order
     */
    #[Override]
    public function inputs(): array
    {
        return [$this->start, $this->end];
    }

    /**
     * Returns the function name.
     */
    #[Override]
    public function spelling(): string
    {
        return 'TIMESTAMPDIFF';
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
            throw new InvalidStructure('TIMESTAMPDIFF facts are derived from its unit and operands.');
        }
        return new self($this->source, $this->unit, $this->start, $this->end);
    }
}
