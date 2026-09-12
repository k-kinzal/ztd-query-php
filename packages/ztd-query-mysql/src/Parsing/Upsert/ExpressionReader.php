<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Parsing\Upsert;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Shadow\Mutation\UpsertColumnSource;
use ZtdQuery\Shadow\Mutation\UpsertExpression;
use ZtdQuery\Shadow\Mutation\UpsertExpressionKind;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;

/**
 * Expression Reader.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class ExpressionReader
{
    /**
     * @param list<SqlToken> $tokens
     */
    public function parseOr(
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

    /**
     * @param list<SqlToken> $tokens
     */
    public function parseAnd(
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

    /**
     * @param list<SqlToken> $tokens
     */
    public function parseComparison(
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

    /**
     * @param list<SqlToken> $tokens
     */
    public function parseAdditive(
        string $sql,
        string $tableName,
        ?string $incomingAlias,
        array $tokens,
        int &$index,
    ): UpsertExpression {
        $left = $this->parseMultiplicative($sql, $tableName, $incomingAlias, $tokens, $index);
        while (isset($tokens[$index]) && ($tokens[$index]->kind === SqlTokenKind::Symbol && in_array($tokens[$index]->text, ['+', '-'], true))) {
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

    /**
     * @param list<SqlToken> $tokens
     */
    public function parseMultiplicative(
        string $sql,
        string $tableName,
        ?string $incomingAlias,
        array $tokens,
        int &$index,
    ): UpsertExpression {
        $left = $this->parseUnary($sql, $tableName, $incomingAlias, $tokens, $index);
        while (isset($tokens[$index]) && ($tokens[$index]->kind === SqlTokenKind::Symbol && in_array($tokens[$index]->text, ['*', '/', '%'], true))) {
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

    /**
     * @param list<SqlToken> $tokens
     */
    public function parseUnary(
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
        if ($token !== null && ($token->kind === SqlTokenKind::Symbol && in_array($token->text, ['+', '-'], true))) {
            $index++;

            return UpsertExpression::unary(
                $token->text === '+' ? UpsertExpressionKind::UnaryPlus : UpsertExpressionKind::UnaryMinus,
                $this->parseUnary($sql, $tableName, $incomingAlias, $tokens, $index),
            );
        }

        return $this->parsePrimary($sql, $tableName, $incomingAlias, $tokens, $index);
    }

    /**
     * @param list<SqlToken> $tokens
     * @throws UnsupportedSqlException
     */
    public function parsePrimary(
        string $sql,
        string $tableName,
        ?string $incomingAlias,
        array $tokens,
        int &$index,
    ): UpsertExpression {
        $token = $tokens[$index] ?? null;
        if ($token === null) {
            throw (new UnsupportedSqlException($sql, 'Unsupported UPSERT expression'));
        }
        if (($token->kind === SqlTokenKind::Symbol && in_array($token->text, ['('], true))) {
            $index++;
            $expression = $this->parseOr($sql, $tableName, $incomingAlias, $tokens, $index);
            if (!isset($tokens[$index]) || !($tokens[$index]->kind === SqlTokenKind::Symbol && in_array($tokens[$index]->text, [')'], true))) {
                throw (new UnsupportedSqlException($sql, 'Unsupported UPSERT expression'));
            }
            $index++;

            return $expression;
        }
        if ($token->kind === SqlTokenKind::Number) {
            $index++;

            return UpsertExpression::literal((new LiteralReader())->number($token->text));
        }
        if ($token->kind === SqlTokenKind::String) {
            $index++;

            return UpsertExpression::literal((new StringLiteral())->string($token->text));
        }
        if ($token->isKeyword('NULL')) {
            $index++;

            return UpsertExpression::literal(null);
        }
        if ($token->isKeyword('TRUE') || $token->isKeyword('FALSE')) {
            $index++;

            return UpsertExpression::literal($token->isKeyword('TRUE'));
        }
        if (!($token->kind === SqlTokenKind::Word || $token->kind === SqlTokenKind::QuotedIdentifier)) {
            throw (new UnsupportedSqlException($sql, 'Unsupported UPSERT expression'));
        }

        return $this->parseColumnReference($sql, $tableName, $incomingAlias, $tokens, $index);

    }

    /**
     * @param list<SqlToken> $tokens
     * @throws UnsupportedSqlException
     */
    public function parseValuesReference(string $sql, array $tokens, int &$index): UpsertExpression
    {
        $index++;
        $column = $tokens[$index] ?? null;
        if ($column === null || !($column->kind === SqlTokenKind::Word || $column->kind === SqlTokenKind::QuotedIdentifier)) {
            throw (new UnsupportedSqlException($sql, 'Unsupported UPSERT expression'));
        }
        $index++;
        if (!isset($tokens[$index]) || !($tokens[$index]->kind === SqlTokenKind::Symbol && in_array($tokens[$index]->text, [')'], true))) {
            throw (new UnsupportedSqlException($sql, 'Unsupported UPSERT expression'));
        }
        $index++;

        return UpsertExpression::column(UpsertColumnSource::Incoming, (new LiteralReader())->identifier($column));
    }

    /**
     * Column Source for the supplied MySQL input.
     * @throws UnsupportedSqlException
     */
    public function columnSource(
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

        throw (new UnsupportedSqlException($sql, 'Unsupported UPSERT expression'));
    }

    /**
     * @param list<SqlToken> $tokens
     * @throws UnsupportedSqlException
     */
    public function comparisonOperator(string $sql, array $tokens, int &$index): ?UpsertExpressionKind
    {
        $first = $tokens[$index] ?? null;
        if ($first === null || !($first->kind === SqlTokenKind::Symbol && in_array($first->text, ['=', '!', '<', '>'], true))) {
            return null;
        }
        $operator = $first->text;
        $second = $tokens[$index + 1] ?? null;
        if ($second !== null && ($second->kind === SqlTokenKind::Symbol && in_array($second->text, ['=', '>'], true)) && $operator !== '=') {
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
            '!', '!>', '>>' => throw (new UnsupportedSqlException($sql, 'Unsupported UPSERT expression')),
        };
    }

    /**
     * @param list<SqlToken> $tokens
     * @throws UnsupportedSqlException
     */
    public function parseColumnReference(string $sql, string $tableName, ?string $incomingAlias, array $tokens, int &$index): UpsertExpression
    {
        $token = $tokens[$index];
        $identifier = (new LiteralReader())->identifier($token);
        $index++;
        if (strcasecmp($identifier, 'VALUES') === 0) {
            if (!isset($tokens[$index]) || !($tokens[$index]->kind === SqlTokenKind::Symbol && in_array($tokens[$index]->text, ['('], true))) {
                throw (new UnsupportedSqlException($sql, 'Unsupported UPSERT expression'));
            }

            return $this->parseValuesReference($sql, $tokens, $index);
        }
        if (isset($tokens[$index]) && ($tokens[$index]->kind === SqlTokenKind::Symbol && in_array($tokens[$index]->text, ['.'], true))) {
            $index++;
            $column = $tokens[$index] ?? null;
            if ($column === null || !($column->kind === SqlTokenKind::Word || $column->kind === SqlTokenKind::QuotedIdentifier)) {
                throw (new UnsupportedSqlException($sql, 'Unsupported UPSERT expression'));
            }
            $index++;

            return UpsertExpression::column(
                $this->columnSource($sql, $identifier, $tableName, $incomingAlias),
                (new LiteralReader())->identifier($column),
            );
        }

        return UpsertExpression::column(UpsertColumnSource::Existing, $identifier);
    }

}
