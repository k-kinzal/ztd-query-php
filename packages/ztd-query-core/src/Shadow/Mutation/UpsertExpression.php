<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use InvalidArgumentException;
use ZtdQuery\Exception\UnsupportedSqlException;

/**
 * Dialect-independent scalar expression used by an UPSERT assignment.
 */
final class UpsertExpression
{
    /** @param list<self> $operands */
    private function __construct(
        private readonly UpsertExpressionKind $kind,
        private readonly mixed $literal = null,
        private readonly ?UpsertColumnSource $columnSource = null,
        private readonly ?string $column = null,
        private readonly array $operands = [],
    ) {
    }

    public static function literal(mixed $value): self
    {
        return new self(UpsertExpressionKind::Literal, literal: $value);
    }

    public static function column(UpsertColumnSource $source, string $column): self
    {
        if ($column === '') {
            throw new InvalidArgumentException('UPSERT column must not be empty');
        }

        return new self(UpsertExpressionKind::Column, columnSource: $source, column: $column);
    }

    public static function unary(UpsertExpressionKind $kind, self $operand): self
    {
        if (!in_array($kind, [
            UpsertExpressionKind::UnaryPlus,
            UpsertExpressionKind::UnaryMinus,
            UpsertExpressionKind::Not,
        ], true)) {
            throw new InvalidArgumentException('Expected a unary UPSERT expression kind');
        }

        return new self($kind, operands: [$operand]);
    }

    public static function binary(UpsertExpressionKind $kind, self $left, self $right): self
    {
        if (in_array($kind, [
            UpsertExpressionKind::Literal,
            UpsertExpressionKind::Column,
            UpsertExpressionKind::UnaryPlus,
            UpsertExpressionKind::UnaryMinus,
            UpsertExpressionKind::Not,
        ], true)) {
            throw new InvalidArgumentException('Expected a binary UPSERT expression kind');
        }

        return new self($kind, operands: [$left, $right]);
    }

    /**
     * @param array<string, mixed> $existingRow
     * @param array<string, mixed> $incomingRow
     */
    public function evaluate(array $existingRow, array $incomingRow, string $tableName): mixed
    {
        return match ($this->kind) {
            UpsertExpressionKind::Literal => $this->literal,
            UpsertExpressionKind::Column => $this->resolveColumn($existingRow, $incomingRow),
            UpsertExpressionKind::UnaryPlus => self::unaryPlus($this->operand(0, $existingRow, $incomingRow, $tableName)),
            UpsertExpressionKind::UnaryMinus => self::unaryMinus($this->operand(0, $existingRow, $incomingRow, $tableName)),
            UpsertExpressionKind::Not => self::not($this->operand(0, $existingRow, $incomingRow, $tableName)),
            UpsertExpressionKind::Add => self::add(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::Subtract => self::subtract(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::Multiply => self::multiply(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::Divide => self::divide(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::Modulo => self::modulo(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::Equal => self::equal(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::NotEqual => self::notEqual(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::Less => self::less(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::LessOrEqual => self::lessOrEqual(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::Greater => self::greater(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::GreaterOrEqual => self::greaterOrEqual(
                $this->operand(0, $existingRow, $incomingRow, $tableName),
                $this->operand(1, $existingRow, $incomingRow, $tableName),
            ),
            UpsertExpressionKind::And => self::and(
                self::truth($this->operand(0, $existingRow, $incomingRow, $tableName)),
                self::truth($this->operand(1, $existingRow, $incomingRow, $tableName)),
            ),
            UpsertExpressionKind::Or => self::or(
                self::truth($this->operand(0, $existingRow, $incomingRow, $tableName)),
                self::truth($this->operand(1, $existingRow, $incomingRow, $tableName)),
            ),
        };
    }

    /**
     * @param array<string, mixed> $existingRow
     * @param array<string, mixed> $incomingRow
     */
    public function matches(array $existingRow, array $incomingRow, string $tableName): bool
    {
        return self::truth($this->evaluate($existingRow, $incomingRow, $tableName)) === true;
    }

    /**
     * @param array<string, mixed> $existingRow
     * @param array<string, mixed> $incomingRow
     */
    private function resolveColumn(array $existingRow, array $incomingRow): mixed
    {
        $column = $this->column ?? '';
        if ($this->columnSource === UpsertColumnSource::Incoming) {
            return self::rowValue($incomingRow, $column);
        }

        return self::rowValue($existingRow, $column);
    }

    /**
     * @param array<string, mixed> $existingRow
     * @param array<string, mixed> $incomingRow
     */
    private function operand(int $index, array $existingRow, array $incomingRow, string $tableName): mixed
    {
        return $this->operands[$index]->evaluate($existingRow, $incomingRow, $tableName);
    }

    /** @param array<string, mixed> $row */
    private static function rowValue(array $row, string $column): mixed
    {
        foreach ($row as $name => $value) {
            if (strcasecmp($name, $column) === 0) {
                return $value;
            }
        }

        throw self::unsupported('unknown UPSERT column ' . $column);
    }

    private static function numeric(mixed $value): int|float
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return $value;
        }
        if (!is_string($value) || !is_numeric($value)) {
            throw self::unsupported('non-numeric UPSERT operand');
        }

        return strpbrk($value, '.eE') === false ? (int) $value : (float) $value;
    }

    private static function unaryPlus(mixed $value): int|float|null
    {
        return $value === null ? null : self::numeric($value);
    }

    private static function unaryMinus(mixed $value): int|float|null
    {
        return $value === null ? null : -self::numeric($value);
    }

    private static function not(mixed $value): ?bool
    {
        $truth = self::truth($value);

        return $truth === null ? null : !$truth;
    }

    private static function add(mixed $left, mixed $right): int|float|null
    {
        if ($left === null || $right === null) {
            return null;
        }

        return self::numeric($left) + self::numeric($right);
    }

    private static function subtract(mixed $left, mixed $right): int|float|null
    {
        if ($left === null || $right === null) {
            return null;
        }

        return self::numeric($left) - self::numeric($right);
    }

    private static function multiply(mixed $left, mixed $right): int|float|null
    {
        if ($left === null || $right === null) {
            return null;
        }

        return self::numeric($left) * self::numeric($right);
    }

    private static function divide(mixed $left, mixed $right): float|int|null
    {
        if ($left === null || $right === null) {
            return null;
        }
        $divisor = self::numeric($right);
        if ($divisor === 0 || $divisor === 0.0) {
            throw self::unsupported('division by zero in UPSERT expression');
        }

        return self::numeric($left) / $divisor;
    }

    private static function modulo(mixed $left, mixed $right): ?int
    {
        if ($left === null || $right === null) {
            return null;
        }
        $divisor = intval(self::numeric($right));
        if ($divisor === 0) {
            throw self::unsupported('division by zero in UPSERT expression');
        }

        return intval(self::numeric($left)) % $divisor;
    }

    private static function comparison(mixed $left, mixed $right): ?int
    {
        if ($left === null || $right === null) {
            return null;
        }
        if (self::isNumericValue($left)) {
            if (!self::isNumericValue($right)) {
                throw self::unsupported('incomparable UPSERT operands');
            }

            return self::numeric($left) <=> self::numeric($right);
        }
        if (self::isNumericValue($right)) {
            throw self::unsupported('incomparable UPSERT operands');
        }

        return self::text($left) <=> self::text($right);
    }

    private static function equal(mixed $left, mixed $right): ?bool
    {
        $comparison = self::comparison($left, $right);

        return $comparison === null ? null : $comparison === 0;
    }

    private static function notEqual(mixed $left, mixed $right): ?bool
    {
        $comparison = self::comparison($left, $right);

        return $comparison === null ? null : $comparison !== 0;
    }

    private static function less(mixed $left, mixed $right): ?bool
    {
        $comparison = self::comparison($left, $right);

        return $comparison === null ? null : $comparison < 0;
    }

    private static function lessOrEqual(mixed $left, mixed $right): ?bool
    {
        $comparison = self::comparison($left, $right);

        return $comparison === null ? null : $comparison <= 0;
    }

    private static function greater(mixed $left, mixed $right): ?bool
    {
        $comparison = self::comparison($left, $right);

        return $comparison === null ? null : $comparison > 0;
    }

    private static function greaterOrEqual(mixed $left, mixed $right): ?bool
    {
        $comparison = self::comparison($left, $right);

        return $comparison === null ? null : $comparison >= 0;
    }

    private static function isNumericValue(mixed $value): bool
    {
        if (is_int($value) || is_float($value)) {
            return true;
        }

        return is_string($value) && is_numeric($value);
    }

    private static function text(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        throw self::unsupported('incomparable UPSERT operands');
    }

    private static function truth(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }
        $numeric = self::numeric($value);

        return !in_array($numeric, [0, 0.0], true);
    }

    private static function and(?bool $left, ?bool $right): ?bool
    {
        if ($left === false || $right === false) {
            return false;
        }
        if ($left === null || $right === null) {
            return null;
        }

        return true;
    }

    private static function or(?bool $left, ?bool $right): ?bool
    {
        if ($left === true || $right === true) {
            return true;
        }
        if ($left === null || $right === null) {
            return null;
        }

        return false;
    }

    private static function unsupported(string $sql): UnsupportedSqlException
    {
        return new UnsupportedSqlException($sql, 'Unsupported UPSERT expression');
    }
}
