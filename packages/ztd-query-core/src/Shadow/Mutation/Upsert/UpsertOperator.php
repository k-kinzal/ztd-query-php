<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation\Upsert;

use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Shadow\Mutation\UpsertExpressionKind;

/**
 * What one operator stands for, once its operands have been worked out.
 *
 * An expression is a tree, and walking it is one job; saying what a plus or
 * a comparison means over two values it has already reached is another. This
 * is the second, so that neither the walk nor any one operator has to know
 * about the rest.
 *
 * @phpstan-import-type RowValue from TableDefinition
 */
final class UpsertOperator
{
    /**
     * @param UpsertNumber $numbers Does the arithmetic
     * @param UpsertComparison $comparisons Does the comparing
     * @param UpsertTruth $truth Answers what counts as true
     */
    public function __construct(
        private readonly UpsertNumber $numbers = new UpsertNumber(),
        private readonly UpsertComparison $comparisons = new UpsertComparison(),
        private readonly UpsertTruth $truth = new UpsertTruth(),
    ) {
    }

    /**
     * Answers what an operator stands for over the values it was written between.
     *
     * @param UpsertExpressionKind $kind Operator to apply
     * @param RowValue $left Value on its left, or its only value
     * @param RowValue $right Value on its right, and null where it has only one
     *
     * @return int|float|bool|null What the operator stands for, and null where it stands for nothing knowable
     *
     * @throws InvalidDefinitionException When the kind names no operator at all
     * @throws UnsupportedSqlException When a value is not something the operator can be applied to
     */
    public function apply(
        UpsertExpressionKind $kind,
        int|float|string|bool|null $left,
        int|float|string|bool|null $right,
    ): int|float|bool|null {
        return match ($kind) {
            UpsertExpressionKind::Literal,
            UpsertExpressionKind::Column => throw new InvalidDefinitionException(
                'Expected an UPSERT expression kind naming an operator',
            ),
            UpsertExpressionKind::UnaryPlus => $this->numbers->positive($left),
            UpsertExpressionKind::UnaryMinus => $this->numbers->negative($left),
            UpsertExpressionKind::Not => $this->truth->not($left),
            UpsertExpressionKind::Add => $this->numbers->add($left, $right),
            UpsertExpressionKind::Subtract => $this->numbers->subtract($left, $right),
            UpsertExpressionKind::Multiply => $this->numbers->multiply($left, $right),
            UpsertExpressionKind::Divide => $this->numbers->divide($left, $right),
            UpsertExpressionKind::Modulo => $this->numbers->modulo($left, $right),
            UpsertExpressionKind::Equal => $this->comparisons->equal($left, $right),
            UpsertExpressionKind::NotEqual => $this->comparisons->notEqual($left, $right),
            UpsertExpressionKind::Less => $this->comparisons->less($left, $right),
            UpsertExpressionKind::LessOrEqual => $this->comparisons->lessOrEqual($left, $right),
            UpsertExpressionKind::Greater => $this->comparisons->greater($left, $right),
            UpsertExpressionKind::GreaterOrEqual => $this->comparisons->greaterOrEqual($left, $right),
            UpsertExpressionKind::And => $this->truth->and($this->truth->of($left), $this->truth->of($right)),
            UpsertExpressionKind::Or => $this->truth->or($this->truth->of($left), $this->truth->of($right)),
        };
    }
}
