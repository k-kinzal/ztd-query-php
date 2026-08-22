<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Shadow\Mutation\UpsertColumnSource;
use ZtdQuery\Shadow\Mutation\UpsertExpression;
use ZtdQuery\Shadow\Mutation\UpsertExpressionKind;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

final class MySqlUpsertExpressionParser
{
    public function parse(string $sql, string $tableName, ?string $incomingAlias = null): UpsertExpression
    {
        $tokens = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create())->significantTokens();
        $index = 0;
        $expression = $this->parseOr($sql, $tableName, $incomingAlias, $tokens, $index);
        if ($index !== count($tokens)) {
            throw $this->unsupported($sql);
        }

        return $expression;
    }

    public function parseIfSupported(string $sql, string $tableName, ?string $incomingAlias = null): ?UpsertExpression
    {
        try {
            return $this->parse($sql, $tableName, $incomingAlias);
        } catch (UnsupportedSqlException) {
            return null;
        }
    }

    /** @param list<SqlToken> $tokens */
    private function parseOr(
        string $sql,
        string $tableName,
        ?string $incomingAlias,
        array $tokens,
        int &$index,
    ): UpsertExpression {
        $left = $this->parseAnd($sql, $tableName, $incomingAlias, $tokens, $index);
        while (($tokens[$index] ?? null)?->isKeyword('OR') === true) {
            $index++;
            $left = UpsertExpression::binary(
                UpsertExpressionKind::Or,
                $left,
                $this->parseAnd($sql, $tableName, $incomingAlias, $tokens, $index),
            );
        }

        return $left;
    }

    /** @param list<SqlToken> $tokens */
    private function parseAnd(
        string $sql,
        string $tableName,
        ?string $incomingAlias,
        array $tokens,
        int &$index,
    ): UpsertExpression {
        $left = $this->parseComparison($sql, $tableName, $incomingAlias, $tokens, $index);
        while (($tokens[$index] ?? null)?->isKeyword('AND') === true) {
            $index++;
            $left = UpsertExpression::binary(
                UpsertExpressionKind::And,
                $left,
                $this->parseComparison($sql, $tableName, $incomingAlias, $tokens, $index),
            );
        }

        return $left;
    }

    /** @param list<SqlToken> $tokens */
    private function parseComparison(
        string $sql,
        string $tableName,
        ?string $incomingAlias,
        array $tokens,
        int &$index,
    ): UpsertExpression {
        $left = $this->parseAdditive($sql, $tableName, $incomingAlias, $tokens, $index);
        $operator = $this->comparisonOperator($sql, $tokens, $index);
        if ($operator === null) {
            return $left;
        }

        return UpsertExpression::binary(
            $operator,
            $left,
            $this->parseAdditive($sql, $tableName, $incomingAlias, $tokens, $index),
        );
    }

    /** @param list<SqlToken> $tokens */
    private function parseAdditive(
        string $sql,
        string $tableName,
        ?string $incomingAlias,
        array $tokens,
        int &$index,
    ): UpsertExpression {
        $left = $this->parseMultiplicative($sql, $tableName, $incomingAlias, $tokens, $index);
        while (isset($tokens[$index]) && $this->isSymbol($tokens[$index], ['+', '-'])) {
            $operator = $tokens[$index]->text;
            $index++;
            $kind = $operator === '+' ? UpsertExpressionKind::Add : UpsertExpressionKind::Subtract;
            $left = UpsertExpression::binary(
                $kind,
                $left,
                $this->parseMultiplicative($sql, $tableName, $incomingAlias, $tokens, $index),
            );
        }

        return $left;
    }

    /** @param list<SqlToken> $tokens */
    private function parseMultiplicative(
        string $sql,
        string $tableName,
        ?string $incomingAlias,
        array $tokens,
        int &$index,
    ): UpsertExpression {
        $left = $this->parseUnary($sql, $tableName, $incomingAlias, $tokens, $index);
        while (isset($tokens[$index]) && $this->isSymbol($tokens[$index], ['*', '/', '%'])) {
            $operator = $tokens[$index]->text;
            $index++;
            $kind = $operator === '*'
                ? UpsertExpressionKind::Multiply
                : ($operator === '/' ? UpsertExpressionKind::Divide : UpsertExpressionKind::Modulo);
            $left = UpsertExpression::binary(
                $kind,
                $left,
                $this->parseUnary($sql, $tableName, $incomingAlias, $tokens, $index),
            );
        }

        return $left;
    }

    /** @param list<SqlToken> $tokens */
    private function parseUnary(
        string $sql,
        string $tableName,
        ?string $incomingAlias,
        array $tokens,
        int &$index,
    ): UpsertExpression {
        $token = $tokens[$index] ?? null;
        if ($token?->isKeyword('NOT') === true) {
            $index++;

            return UpsertExpression::unary(
                UpsertExpressionKind::Not,
                $this->parseUnary($sql, $tableName, $incomingAlias, $tokens, $index),
            );
        }
        if ($token !== null && $this->isSymbol($token, ['+', '-'])) {
            $index++;

            return UpsertExpression::unary(
                $token->text === '+' ? UpsertExpressionKind::UnaryPlus : UpsertExpressionKind::UnaryMinus,
                $this->parseUnary($sql, $tableName, $incomingAlias, $tokens, $index),
            );
        }

        return $this->parsePrimary($sql, $tableName, $incomingAlias, $tokens, $index);
    }

    /** @param list<SqlToken> $tokens */
    private function parsePrimary(
        string $sql,
        string $tableName,
        ?string $incomingAlias,
        array $tokens,
        int &$index,
    ): UpsertExpression {
        $token = $tokens[$index] ?? null;
        if ($token === null) {
            throw $this->unsupported($sql);
        }
        if ($this->isSymbol($token, ['('])) {
            $index++;
            $expression = $this->parseOr($sql, $tableName, $incomingAlias, $tokens, $index);
            if (!isset($tokens[$index]) || !$this->isSymbol($tokens[$index], [')'])) {
                throw $this->unsupported($sql);
            }
            $index++;

            return $expression;
        }
        if ($token->kind === SqlTokenKind::Number) {
            $index++;

            return UpsertExpression::literal($this->number($token->text));
        }
        if ($token->kind === SqlTokenKind::String) {
            $index++;

            return UpsertExpression::literal($this->string($token->text));
        }
        if ($token->isKeyword('NULL')) {
            $index++;

            return UpsertExpression::literal(null);
        }
        if ($token->isKeyword('TRUE') || $token->isKeyword('FALSE')) {
            $index++;

            return UpsertExpression::literal($token->isKeyword('TRUE'));
        }
        if (!$this->isIdentifier($token)) {
            throw $this->unsupported($sql);
        }

        $identifier = $this->identifier($token);
        $index++;
        if (strcasecmp($identifier, 'VALUES') === 0) {
            if (!isset($tokens[$index]) || !$this->isSymbol($tokens[$index], ['('])) {
                throw $this->unsupported($sql);
            }

            return $this->parseValuesReference($sql, $tokens, $index);
        }
        if (isset($tokens[$index]) && $this->isSymbol($tokens[$index], ['.'])) {
            $index++;
            $column = $tokens[$index] ?? null;
            if ($column === null || !$this->isIdentifier($column)) {
                throw $this->unsupported($sql);
            }
            $index++;

            return UpsertExpression::column(
                $this->columnSource($sql, $identifier, $tableName, $incomingAlias),
                $this->identifier($column),
            );
        }

        return UpsertExpression::column(UpsertColumnSource::Existing, $identifier);
    }

    /** @param list<SqlToken> $tokens */
    private function parseValuesReference(string $sql, array $tokens, int &$index): UpsertExpression
    {
        $index++;
        $column = $tokens[$index] ?? null;
        if ($column === null || !$this->isIdentifier($column)) {
            throw $this->unsupported($sql);
        }
        $index++;
        if (!isset($tokens[$index]) || !$this->isSymbol($tokens[$index], [')'])) {
            throw $this->unsupported($sql);
        }
        $index++;

        return UpsertExpression::column(UpsertColumnSource::Incoming, $this->identifier($column));
    }

    private function columnSource(
        string $sql,
        string $qualifier,
        string $tableName,
        ?string $incomingAlias,
    ): UpsertColumnSource {
        if ($incomingAlias !== null && strcasecmp($qualifier, $incomingAlias) === 0) {
            return UpsertColumnSource::Incoming;
        }
        if (strcasecmp($qualifier, $tableName) === 0) {
            return UpsertColumnSource::Existing;
        }

        throw $this->unsupported($sql);
    }

    /** @param list<SqlToken> $tokens */
    private function comparisonOperator(string $sql, array $tokens, int &$index): ?UpsertExpressionKind
    {
        $first = $tokens[$index] ?? null;
        if ($first === null || !$this->isSymbol($first, ['=', '!', '<', '>'])) {
            return null;
        }
        $operator = $first->text;
        $second = $tokens[$index + 1] ?? null;
        if ($second !== null && $this->isSymbol($second, ['=', '>']) && $operator !== '=') {
            $operator .= $second->text;
            $index++;
        }
        $index++;

        return match ($operator) {
            '=' => UpsertExpressionKind::Equal,
            '!=', '<>' => UpsertExpressionKind::NotEqual,
            '<' => UpsertExpressionKind::Less,
            '<=' => UpsertExpressionKind::LessOrEqual,
            '>' => UpsertExpressionKind::Greater,
            '>=' => UpsertExpressionKind::GreaterOrEqual,
            default => throw $this->unsupported($sql),
        };
    }

    /** @param list<string> $symbols */
    private function isSymbol(SqlToken $token, array $symbols): bool
    {
        return $token->kind === SqlTokenKind::Symbol && in_array($token->text, $symbols, true);
    }

    private function isIdentifier(SqlToken $token): bool
    {
        return $token->kind === SqlTokenKind::Word || $token->kind === SqlTokenKind::QuotedIdentifier;
    }

    private function identifier(SqlToken $token): string
    {
        if ($token->kind === SqlTokenKind::Word) {
            return $token->text;
        }
        $quote = $token->text[0] ?? '';
        $inner = substr($token->text, 1, -1);

        return str_replace($quote . $quote, $quote, $inner);
    }

    private function number(string $literal): int|float
    {
        if (preg_match('/^(?:0x[0-9A-Fa-f]+|(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?)\z/', $literal) !== 1) {
            throw $this->unsupported($literal);
        }
        if (str_starts_with($literal, '0x')) {
            return intval($literal, 16);
        }

        return strpbrk($literal, '.eE') === false ? (int) $literal : (float) $literal;
    }

    private function string(string $literal): string
    {
        $inner = substr($literal, 1, -1);

        return str_replace(["''", "\\'", '\\\\'], ["'", "'", '\\'], $inner);
    }

    private function unsupported(string $sql): UnsupportedSqlException
    {
        return new UnsupportedSqlException($sql, 'Unsupported UPSERT expression');
    }
}
