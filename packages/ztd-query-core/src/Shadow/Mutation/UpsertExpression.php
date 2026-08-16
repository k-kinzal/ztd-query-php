<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Parsed scalar expression used by an UPSERT assignment.
 */
final class UpsertExpression
{
    private const LITERAL = 'literal';
    private const COLUMN = 'column';
    private const UNARY = 'unary';
    private const BINARY = 'binary';

    private function __construct(
        private readonly string $kind,
        private readonly mixed $literal = null,
        private readonly ?string $qualifier = null,
        private readonly ?string $column = null,
        private readonly ?string $operator = null,
        private readonly ?self $left = null,
        private readonly ?self $right = null,
    ) {
    }

    public static function parse(string $sql): self
    {
        $tokens = SqlTokenStream::tokenize($sql)->significantTokens();
        $index = 0;
        $expression = self::parseOr($sql, $tokens, $index);
        if (isset($tokens[$index])) {
            throw self::unsupported($sql);
        }

        return $expression;
    }

    /**
     * @param array<string, mixed> $existingRow
     * @param array<string, mixed> $incomingRow
     */
    public function evaluate(array $existingRow, array $incomingRow, string $tableName): mixed
    {
        return match ($this->kind) {
            self::LITERAL => $this->literal,
            self::COLUMN => $this->resolveColumn($existingRow, $incomingRow, $tableName),
            self::UNARY => $this->evaluateUnary($existingRow, $incomingRow, $tableName),
            self::BINARY => $this->evaluateBinary($existingRow, $incomingRow, $tableName),
            default => throw self::unsupported($this->kind),
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
     * @param list<SqlToken> $tokens
     */
    private static function parseOr(string $sql, array $tokens, int &$index): self
    {
        $left = self::parseAnd($sql, $tokens, $index);
        while (($tokens[$index] ?? null)?->isKeyword('OR') === true) {
            $index++;
            $left = self::binary('OR', $left, self::parseAnd($sql, $tokens, $index));
        }

        return $left;
    }

    /**
     * @param list<SqlToken> $tokens
     */
    private static function parseAnd(string $sql, array $tokens, int &$index): self
    {
        $left = self::parseComparison($sql, $tokens, $index);
        while (($tokens[$index] ?? null)?->isKeyword('AND') === true) {
            $index++;
            $left = self::binary('AND', $left, self::parseComparison($sql, $tokens, $index));
        }

        return $left;
    }

    /**
     * @param list<SqlToken> $tokens
     */
    private static function parseComparison(string $sql, array $tokens, int &$index): self
    {
        $left = self::parseAdditive($sql, $tokens, $index);
        $operator = self::comparisonOperator($sql, $tokens, $index);
        if ($operator === null) {
            return $left;
        }

        return self::binary($operator, $left, self::parseAdditive($sql, $tokens, $index));
    }

    /**
     * @param list<SqlToken> $tokens
     */
    private static function parseAdditive(string $sql, array $tokens, int &$index): self
    {
        $left = self::parseMultiplicative($sql, $tokens, $index);
        while (isset($tokens[$index]) && self::isSymbol($tokens[$index], ['+', '-'])) {
            $operator = $tokens[$index]->text;
            $index++;
            $left = self::binary($operator, $left, self::parseMultiplicative($sql, $tokens, $index));
        }

        return $left;
    }

    /**
     * @param list<SqlToken> $tokens
     */
    private static function parseMultiplicative(string $sql, array $tokens, int &$index): self
    {
        $left = self::parseUnary($sql, $tokens, $index);
        while (isset($tokens[$index]) && self::isSymbol($tokens[$index], ['*', '/', '%'])) {
            $operator = $tokens[$index]->text;
            $index++;
            $left = self::binary($operator, $left, self::parseUnary($sql, $tokens, $index));
        }

        return $left;
    }

    /**
     * @param list<SqlToken> $tokens
     */
    private static function parseUnary(string $sql, array $tokens, int &$index): self
    {
        $token = $tokens[$index] ?? null;
        if ($token?->isKeyword('NOT') === true) {
            $index++;

            return self::unary('NOT', self::parseUnary($sql, $tokens, $index));
        }
        if ($token !== null && self::isSymbol($token, ['+', '-'])) {
            $index++;

            return self::unary($token->text, self::parseUnary($sql, $tokens, $index));
        }

        return self::parsePrimary($sql, $tokens, $index);
    }

    /**
     * @param list<SqlToken> $tokens
     */
    private static function parsePrimary(string $sql, array $tokens, int &$index): self
    {
        $token = $tokens[$index] ?? null;
        if ($token === null) {
            throw self::unsupported($sql);
        }
        if (self::isSymbol($token, ['('])) {
            $index++;
            $expression = self::parseOr($sql, $tokens, $index);
            if (!isset($tokens[$index]) || !self::isSymbol($tokens[$index], [')'])) {
                throw self::unsupported($sql);
            }
            $index++;

            return $expression;
        }
        if ($token->kind === SqlTokenKind::Number) {
            $index++;

            return self::literal(self::number($token->text));
        }
        if ($token->kind === SqlTokenKind::String) {
            $index++;

            return self::literal(self::string($token->text));
        }
        if ($token->isKeyword('NULL')) {
            $index++;

            return self::literal(null);
        }
        if ($token->isKeyword('TRUE') || $token->isKeyword('FALSE')) {
            $index++;

            return self::literal($token->isKeyword('TRUE'));
        }
        if (!self::isIdentifier($token)) {
            throw self::unsupported($sql);
        }

        $identifier = self::identifier($token);
        $index++;
        if (strcasecmp($identifier, 'VALUES') === 0 && isset($tokens[$index]) && self::isSymbol($tokens[$index], ['('])) {
            return self::parseValuesReference($sql, $tokens, $index);
        }
        if (isset($tokens[$index]) && self::isSymbol($tokens[$index], ['.'])) {
            $index++;
            $column = $tokens[$index] ?? null;
            if ($column === null || !self::isIdentifier($column)) {
                throw self::unsupported($sql);
            }
            $index++;

            return self::column($identifier, self::identifier($column));
        }

        return self::column(null, $identifier);
    }

    /**
     * @param list<SqlToken> $tokens
     */
    private static function parseValuesReference(string $sql, array $tokens, int &$index): self
    {
        $index++;
        $column = $tokens[$index] ?? null;
        if ($column === null || !self::isIdentifier($column)) {
            throw self::unsupported($sql);
        }
        $index++;
        if (!isset($tokens[$index]) || !self::isSymbol($tokens[$index], [')'])) {
            throw self::unsupported($sql);
        }
        $index++;

        return self::column('VALUES', self::identifier($column));
    }

    /**
     * @param list<SqlToken> $tokens
     */
    private static function comparisonOperator(string $sql, array $tokens, int &$index): ?string
    {
        $first = $tokens[$index] ?? null;
        if ($first === null || !self::isSymbol($first, ['=', '!', '<', '>'])) {
            return null;
        }
        $operator = $first->text;
        $second = $tokens[$index + 1] ?? null;
        if ($second !== null && self::isSymbol($second, ['=', '>']) && $operator !== '=') {
            $operator .= $second->text;
            $index++;
        }
        $index++;

        if (!in_array($operator, ['=', '!=', '<>', '<', '<=', '>', '>='], true)) {
            throw self::unsupported($sql);
        }

        return $operator;
    }

    /**
     * @param array<string, mixed> $existingRow
     * @param array<string, mixed> $incomingRow
     */
    private function resolveColumn(array $existingRow, array $incomingRow, string $tableName): mixed
    {
        $column = $this->column ?? '';
        if ($this->qualifier !== null
            && (strcasecmp($this->qualifier, 'EXCLUDED') === 0 || strcasecmp($this->qualifier, 'VALUES') === 0)
        ) {
            return self::rowValue($incomingRow, $column);
        }
        if ($this->qualifier !== null && strcasecmp($this->qualifier, $tableName) !== 0) {
            throw self::unsupported($this->qualifier . '.' . $column);
        }

        return self::rowValue($existingRow, $column);
    }

    /**
     * @param array<string, mixed> $existingRow
     * @param array<string, mixed> $incomingRow
     */
    private function evaluateUnary(array $existingRow, array $incomingRow, string $tableName): mixed
    {
        $value = $this->left?->evaluate($existingRow, $incomingRow, $tableName);

        return match ($this->operator) {
            '+' => $value === null ? null : self::numeric($value),
            '-' => $value === null ? null : -self::numeric($value),
            'NOT' => ($truth = self::truth($value)) === null ? null : !$truth,
            default => throw self::unsupported((string) $this->operator),
        };
    }

    /**
     * @param array<string, mixed> $existingRow
     * @param array<string, mixed> $incomingRow
     */
    private function evaluateBinary(array $existingRow, array $incomingRow, string $tableName): mixed
    {
        $left = $this->left?->evaluate($existingRow, $incomingRow, $tableName);
        $right = $this->right?->evaluate($existingRow, $incomingRow, $tableName);
        if ($this->operator === 'AND' || $this->operator === 'OR') {
            return self::logical($this->operator, self::truth($left), self::truth($right));
        }
        if ($left === null || $right === null) {
            return null;
        }

        return match ($this->operator) {
            '+' => self::numeric($left) + self::numeric($right),
            '-' => self::numeric($left) - self::numeric($right),
            '*' => self::numeric($left) * self::numeric($right),
            '/' => self::divide($left, $right),
            '%' => self::modulo($left, $right),
            '=', '!=', '<>', '<', '<=', '>', '>=' => self::compare($this->operator, $left, $right),
            default => throw self::unsupported((string) $this->operator),
        };
    }

    private static function literal(mixed $value): self
    {
        return new self(self::LITERAL, literal: $value);
    }

    private static function column(?string $qualifier, string $column): self
    {
        return new self(self::COLUMN, qualifier: $qualifier, column: $column);
    }

    private static function unary(string $operator, self $operand): self
    {
        return new self(self::UNARY, operator: $operator, left: $operand);
    }

    private static function binary(string $operator, self $left, self $right): self
    {
        return new self(self::BINARY, operator: $operator, left: $left, right: $right);
    }

    /** @param list<string> $symbols */
    private static function isSymbol(SqlToken $token, array $symbols): bool
    {
        return $token->kind === SqlTokenKind::Symbol && in_array($token->text, $symbols, true);
    }

    private static function isIdentifier(SqlToken $token): bool
    {
        return $token->kind === SqlTokenKind::Word || $token->kind === SqlTokenKind::QuotedIdentifier;
    }

    private static function identifier(SqlToken $token): string
    {
        if ($token->kind === SqlTokenKind::Word) {
            return $token->text;
        }
        $quote = $token->text[0] ?? '';
        $inner = substr($token->text, 1, -1);

        return str_replace($quote . $quote, $quote, $inner);
    }

    private static function number(string $literal): int|float
    {
        $literal = str_replace('_', '', $literal);
        if (str_starts_with(strtolower($literal), '0x')) {
            return intval(substr($literal, 2), 16);
        }

        return strpbrk($literal, '.eE') === false ? (int) $literal : (float) $literal;
    }

    private static function string(string $literal): string
    {
        $inner = substr($literal, 1, -1);

        return str_replace(["''", "\\'", '\\\\'], ["'", "'", '\\'], $inner);
    }

    /** @param array<string, mixed> $row */
    private static function rowValue(array $row, string $column): mixed
    {
        if (array_key_exists($column, $row)) {
            return $row[$column];
        }
        foreach ($row as $name => $value) {
            if (strcasecmp($name, $column) === 0) {
                return $value;
            }
        }

        throw self::unsupported('unknown UPSERT column ' . $column);
    }

    private static function numeric(mixed $value): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        if (is_string($value) && is_numeric($value)) {
            return strpbrk($value, '.eE') === false ? (int) $value : (float) $value;
        }

        throw self::unsupported('non-numeric UPSERT operand');
    }

    private static function divide(mixed $left, mixed $right): float|int
    {
        $divisor = self::numeric($right);
        if ($divisor === 0 || $divisor === 0.0) {
            throw self::unsupported('division by zero in UPSERT expression');
        }

        return self::numeric($left) / $divisor;
    }

    private static function modulo(mixed $left, mixed $right): int
    {
        $divisor = (int) self::numeric($right);
        if ($divisor === 0) {
            throw self::unsupported('division by zero in UPSERT expression');
        }

        return (int) self::numeric($left) % $divisor;
    }

    private static function compare(string $operator, mixed $left, mixed $right): bool
    {
        if ((is_int($left) || is_float($left) || is_numeric($left))
            && (is_int($right) || is_float($right) || is_numeric($right))
        ) {
            $comparison = self::numeric($left) <=> self::numeric($right);
        } elseif ((is_string($left) || is_bool($left)) && (is_string($right) || is_bool($right))) {
            $comparison = (string) $left <=> (string) $right;
        } else {
            throw self::unsupported('incomparable UPSERT operands');
        }

        return match ($operator) {
            '=' => $comparison === 0,
            '!=', '<>' => $comparison !== 0,
            '<' => $comparison < 0,
            '<=' => $comparison <= 0,
            '>' => $comparison > 0,
            '>=' => $comparison >= 0,
            default => throw self::unsupported($operator),
        };
    }

    private static function truth(mixed $value): ?bool
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $numeric = self::numeric($value);

            return $numeric !== 0 && $numeric !== 0.0;
        }

        throw self::unsupported('non-boolean UPSERT operand');
    }

    private static function logical(string $operator, ?bool $left, ?bool $right): ?bool
    {
        if ($operator === 'AND') {
            return $left === false || $right === false ? false : ($left === null || $right === null ? null : true);
        }

        return $left === true || $right === true ? true : ($left === null || $right === null ? null : false);
    }

    private static function unsupported(string $sql): UnsupportedSqlException
    {
        return new UnsupportedSqlException($sql, 'Unsupported UPSERT expression');
    }
}
