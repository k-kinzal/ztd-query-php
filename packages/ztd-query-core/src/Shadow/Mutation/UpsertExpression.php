<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertColumn;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertComparison;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertNumber;
use ZtdQuery\Shadow\Mutation\Upsert\UpsertTruth;

/**
 * A scalar expression from an UPSERT assignment, in no particular dialect.
 *
 * Every dialect writes "the value that was already there" and "the value that
 * was coming in" differently, so a parser reads its own spelling and builds one
 * of these; what the expression means is then worked out here, once, for all of
 * them. What each operator means is left to the collaborator that owns it, so
 * this is only the tree and the walk over it.
 *
 * @phpstan-import-type Row from StatementInterface
 * @phpstan-import-type RowValue from StatementInterface
 */
final class UpsertExpression
{
    /**
     * @param UpsertExpressionKind $kind What this node is
     * @param RowValue $literal Value a literal stands for
     * @param UpsertColumnSource|null $columnSource Which row a column is read from
     * @param string|null $column Column a column node names
     * @param list<self> $operands Nodes this one is written over
     * @param UpsertNumber $numbers Does the arithmetic
     * @param UpsertComparison $comparisons Does the comparing
     * @param UpsertTruth $truth Answers what counts as true
     * @param UpsertColumn $columns Reads a column off a row
     */
    private function __construct(
        private readonly UpsertExpressionKind $kind,
        private readonly int|float|string|bool|null $literal = null,
        private readonly ?UpsertColumnSource $columnSource = null,
        private readonly ?string $column = null,
        private readonly array $operands = [],
        private readonly UpsertNumber $numbers = new UpsertNumber(),
        private readonly UpsertComparison $comparisons = new UpsertComparison(),
        private readonly UpsertTruth $truth = new UpsertTruth(),
        private readonly UpsertColumn $columns = new UpsertColumn(),
    ) {
    }

    /**
     * Builds an expression standing for a value written into the statement.
     *
     * @param RowValue $value Value as the statement wrote it
     *
     * @return self The expression
     */
    public static function literal(int|float|string|bool|null $value): self
    {
        return new self(UpsertExpressionKind::Literal, literal: $value);
    }

    /**
     * Builds an expression standing for a column of one of the two rows.
     *
     * @param UpsertColumnSource $source Whether the column is read from the existing row or the incoming one
     * @param string $column Column as the statement named it
     *
     * @return self The expression
     *
     * @throws InvalidDefinitionException When no column was named
     */
    public static function column(UpsertColumnSource $source, string $column): self
    {
        if ($column === '') {
            throw new InvalidDefinitionException('UPSERT column must not be empty');
        }

        return new self(UpsertExpressionKind::Column, columnSource: $source, column: $column);
    }

    /**
     * Builds an expression written over one other.
     *
     * @param UpsertExpressionKind $kind Operator to apply
     * @param self $operand Expression it is applied to
     *
     * @return self The expression
     *
     * @throws InvalidDefinitionException When the operator is not one written over a single operand
     */
    public static function unary(UpsertExpressionKind $kind, self $operand): self
    {
        if (!in_array($kind, [
            UpsertExpressionKind::UnaryPlus,
            UpsertExpressionKind::UnaryMinus,
            UpsertExpressionKind::Not,
        ], true)) {
            throw new InvalidDefinitionException('Expected a unary UPSERT expression kind');
        }

        return new self($kind, operands: [$operand]);
    }

    /**
     * Builds an expression written between two others.
     *
     * @param UpsertExpressionKind $kind Operator to apply
     * @param self $left Expression on its left
     * @param self $right Expression on its right
     *
     * @return self The expression
     *
     * @throws InvalidDefinitionException When the operator is not one written between two operands
     */
    public static function binary(UpsertExpressionKind $kind, self $left, self $right): self
    {
        if (in_array($kind, [
            UpsertExpressionKind::Literal,
            UpsertExpressionKind::Column,
            UpsertExpressionKind::UnaryPlus,
            UpsertExpressionKind::UnaryMinus,
            UpsertExpressionKind::Not,
        ], true)) {
            throw new InvalidDefinitionException('Expected a binary UPSERT expression kind');
        }

        return new self($kind, operands: [$left, $right]);
    }

    /**
     * Answers what the expression stands for, against the two rows in play.
     *
     * @param Row $existingRow Row the conflict was found on
     * @param Row $incomingRow Row the statement was trying to write
     * @param string $tableName Table being written to
     *
     * @return RowValue The value the expression stands for
     *
     * @throws UnsupportedSqlException When an operand is not something the operator can be applied to
     */
    public function evaluate(array $existingRow, array $incomingRow, string $tableName): int|float|string|bool|null
    {
        return match ($this->kind) {
            UpsertExpressionKind::Literal => $this->literal,
            UpsertExpressionKind::Column => $this->columns->of(
                $this->columnSource === UpsertColumnSource::Incoming ? $incomingRow : $existingRow,
                $this->column ?? '',
            ),
            UpsertExpressionKind::UnaryPlus => $this->numbers->positive(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::UnaryMinus => $this->numbers->negative(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::Not => $this->truth->not(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::Add => $this->numbers->add(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::Subtract => $this->numbers->subtract(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::Multiply => $this->numbers->multiply(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::Divide => $this->numbers->divide(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::Modulo => $this->numbers->modulo(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::Equal => $this->comparisons->equal(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::NotEqual => $this->comparisons->notEqual(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::Less => $this->comparisons->less(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::LessOrEqual => $this->comparisons->lessOrEqual(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::Greater => $this->comparisons->greater(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::GreaterOrEqual => $this->comparisons->greaterOrEqual(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::And => $this->truth->and(
                $this->truth->of($this->operand(0, $existingRow, $incomingRow, $tableName)),
                $this->truth->of($this->operand(1, $existingRow, $incomingRow, $tableName)),
            ),
            UpsertExpressionKind::Or => $this->truth->or(
                $this->truth->of($this->operand(0, $existingRow, $incomingRow, $tableName)),
                $this->truth->of($this->operand(1, $existingRow, $incomingRow, $tableName)),
            ),
        };
    }

    /**
     * Reports whether the expression holds, as a condition would have to.
     *
     * Unknown is not enough: a WHERE that cannot be shown to hold does not
     * select the row, so only a definite true counts.
     *
     * @param Row $existingRow Row the conflict was found on
     * @param Row $incomingRow Row the statement was trying to write
     * @param string $tableName Table being written to
     *
     * @return bool True when the expression is definitely true
     *
     * @throws UnsupportedSqlException When an operand is not something the operator can be applied to
     */
    public function matches(array $existingRow, array $incomingRow, string $tableName): bool
    {
        return $this->truth->of($this->evaluate($existingRow, $incomingRow, $tableName)) === true;
    }

    /**
     * Answers what one of the operands stands for.
     *
     * @param int $index Which operand
     * @param Row $existingRow Row the conflict was found on
     * @param Row $incomingRow Row the statement was trying to write
     * @param string $tableName Table being written to
     *
     * @return RowValue The value that operand stands for
     *
     * @throws UnsupportedSqlException When an operand is not something the operator can be applied to
     */
    public function operand(
        int $index,
        array $existingRow,
        array $incomingRow,
        string $tableName,
    ): int|float|string|bool|null {
        return $this->operands[$index]->evaluate($existingRow, $incomingRow, $tableName);
    }
}
