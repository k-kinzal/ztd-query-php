<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Parse;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Shadow\Mutation\UpsertColumnSource;
use ZtdQuery\Shadow\Mutation\UpsertExpression;
use ZtdQuery\Shadow\Mutation\UpsertExpressionKind;
use ZtdQuery\Sql\SqlTokenKind;

/**
 * Reads the expression an ON CONFLICT DO UPDATE assigns, as PostgreSQL groups it.
 *
 * One production per level of precedence, loosest first, each reading the one
 * below it: that is what makes `a OR b AND c` mean `a OR (b AND c)` without
 * anything having to say so. Every production takes the cursor and leaves it
 * just past what it read, so a production that reads nothing leaves it alone.
 */
final class PgSqlUpsertExpressionParser
{
    /**
     * @param PgSqlUpsertLiteral $literals Reads a literal back out of how it was written
     */
    public function __construct(private readonly PgSqlUpsertLiteral $literals = new PgSqlUpsertLiteral())
    {
    }

    /**
     * Reads a whole assignment expression.
     *
     * @param string $sql Expression to read
     * @param string $tableName Table the statement writes to
     * @param string|null $incomingAlias Name the statement gave the incoming row
     *
     * @return UpsertExpression What the expression evaluates
     *
     * @throws UnsupportedSqlException When ZTD cannot read the expression, or anything is left over
     */
    public function parse(string $sql, string $tableName, ?string $incomingAlias = null): UpsertExpression
    {
        $cursor = PgSqlUpsertExpressionCursor::over($sql, $tableName, $incomingAlias);
        $expression = $this->disjunction($cursor);
        if (!$cursor->atEnd()) {
            throw $cursor->unsupported();
        }

        return $expression;
    }

    /**
     * Reads a whole assignment expression, or answers nothing where it cannot.
     *
     * A statement ZTD cannot read the expression of is not a statement ZTD
     * refuses: the database will still evaluate it, and what it evaluated can
     * be read back off the result instead.
     *
     * @param string $sql Expression to read
     * @param string $tableName Table the statement writes to
     * @param string|null $incomingAlias Name the statement gave the incoming row
     *
     * @return UpsertExpression|null What the expression evaluates, or null where ZTD cannot read it
     */
    public function parseIfSupported(string $sql, string $tableName, ?string $incomingAlias = null): ?UpsertExpression
    {
        try {
            return $this->parse($sql, $tableName, $incomingAlias);
        } catch (UnsupportedSqlException) {
            return null;
        }
    }

    /**
     * Reads a run of OR, the loosest thing that binds.
     *
     * @param PgSqlUpsertExpressionCursor $cursor Where the reader has got to
     *
     * @return UpsertExpression What was read
     *
     * @throws UnsupportedSqlException When ZTD cannot read what is written
     */
    public function disjunction(PgSqlUpsertExpressionCursor $cursor): UpsertExpression
    {
        $left = $this->conjunction($cursor);
        while ($cursor->isKeyword('OR')) {
            $cursor->advance();
            $left = UpsertExpression::binary(UpsertExpressionKind::Or, $left, $this->conjunction($cursor));
        }

        return $left;
    }

    /**
     * Reads a run of AND, which binds tighter than OR.
     *
     * @param PgSqlUpsertExpressionCursor $cursor Where the reader has got to
     *
     * @return UpsertExpression What was read
     *
     * @throws UnsupportedSqlException When ZTD cannot read what is written
     */
    public function conjunction(PgSqlUpsertExpressionCursor $cursor): UpsertExpression
    {
        $left = $this->comparison($cursor);
        while ($cursor->isKeyword('AND')) {
            $cursor->advance();
            $left = UpsertExpression::binary(UpsertExpressionKind::And, $left, $this->comparison($cursor));
        }

        return $left;
    }

    /**
     * Reads a comparison, of which SQL allows only one in a row.
     *
     * @param PgSqlUpsertExpressionCursor $cursor Where the reader has got to
     *
     * @return UpsertExpression What was read
     *
     * @throws UnsupportedSqlException When ZTD cannot read what is written
     */
    public function comparison(PgSqlUpsertExpressionCursor $cursor): UpsertExpression
    {
        $left = $this->additive($cursor);
        $operator = $this->comparisonOperator($cursor);
        if ($operator === null) {
            return $left;
        }

        return UpsertExpression::binary($operator, $left, $this->additive($cursor));
    }

    /**
     * Reads a run of addition and subtraction.
     *
     * @param PgSqlUpsertExpressionCursor $cursor Where the reader has got to
     *
     * @return UpsertExpression What was read
     *
     * @throws UnsupportedSqlException When ZTD cannot read what is written
     */
    public function additive(PgSqlUpsertExpressionCursor $cursor): UpsertExpression
    {
        $left = $this->multiplicative($cursor);
        while ($cursor->isSymbol(['+', '-'])) {
            $kind = $cursor->token()?->text === '+' ? UpsertExpressionKind::Add : UpsertExpressionKind::Subtract;
            $cursor->advance();
            $left = UpsertExpression::binary($kind, $left, $this->multiplicative($cursor));
        }

        return $left;
    }

    /**
     * Reads a run of multiplication, division and remainder.
     *
     * @param PgSqlUpsertExpressionCursor $cursor Where the reader has got to
     *
     * @return UpsertExpression What was read
     *
     * @throws UnsupportedSqlException When ZTD cannot read what is written
     */
    public function multiplicative(PgSqlUpsertExpressionCursor $cursor): UpsertExpression
    {
        $left = $this->unary($cursor);
        while ($cursor->isSymbol(['*', '/', '%'])) {
            $operator = $cursor->token()?->text;
            $kind = match ($operator) {
                '*' => UpsertExpressionKind::Multiply,
                '/' => UpsertExpressionKind::Divide,
                default => UpsertExpressionKind::Modulo,
            };
            $cursor->advance();
            $left = UpsertExpression::binary($kind, $left, $this->unary($cursor));
        }

        return $left;
    }

    /**
     * Reads an operator written over one operand, which may be written again over its own.
     *
     * @param PgSqlUpsertExpressionCursor $cursor Where the reader has got to
     *
     * @return UpsertExpression What was read
     *
     * @throws UnsupportedSqlException When ZTD cannot read what is written
     */
    public function unary(PgSqlUpsertExpressionCursor $cursor): UpsertExpression
    {
        $prefixes = [];
        foreach ($cursor->remaining() as $ignored) {
            if ($cursor->isKeyword('NOT')) {
                $prefixes[] = UpsertExpressionKind::Not;
            } elseif ($cursor->isSymbol(['+', '-'])) {
                $prefixes[] = $cursor->token()?->text === '+'
                    ? UpsertExpressionKind::UnaryPlus
                    : UpsertExpressionKind::UnaryMinus;
            } else {
                break;
            }
            $cursor->advance();
        }

        $expression = $this->primary($cursor);
        foreach (array_reverse($prefixes) as $prefix) {
            $expression = UpsertExpression::unary($prefix, $expression);
        }

        return $expression;
    }

    /**
     * Reads the tightest thing there is: a literal, a name, or a whole expression in parentheses.
     *
     * @param PgSqlUpsertExpressionCursor $cursor Where the reader has got to
     *
     * @return UpsertExpression What was read
     *
     * @throws UnsupportedSqlException When ZTD cannot read what is written
     */
    public function primary(PgSqlUpsertExpressionCursor $cursor): UpsertExpression
    {
        $token = $cursor->token();
        if ($token === null) {
            throw $cursor->unsupported();
        }
        if ($cursor->isSymbol(['('])) {
            $inside = $cursor->insideBrackets();
            $expression = $this->disjunction($inside);
            if (!$inside->atEnd()) {
                throw $inside->unsupported();
            }

            return $expression;
        }
        if ($token->kind === SqlTokenKind::Number) {
            $cursor->advance();

            return UpsertExpression::literal($this->literals->numberOf($token->text));
        }
        if ($token->kind === SqlTokenKind::String) {
            $cursor->advance();

            return UpsertExpression::literal($this->literals->textOf($token->text));
        }
        if ($cursor->isKeyword('NULL')) {
            $cursor->advance();

            return UpsertExpression::literal(null);
        }
        if ($cursor->isKeyword('TRUE') || $cursor->isKeyword('FALSE')) {
            $isTrue = $cursor->isKeyword('TRUE');
            $cursor->advance();

            return UpsertExpression::literal($isTrue);
        }

        return $this->named($cursor);
    }

    /**
     * Reads a name, which may be qualified by the table or by EXCLUDED.
     *
     * @param PgSqlUpsertExpressionCursor $cursor Where the reader has got to
     *
     * @return UpsertExpression What was read
     *
     * @throws UnsupportedSqlException When ZTD cannot read what is written
     */
    public function named(PgSqlUpsertExpressionCursor $cursor): UpsertExpression
    {
        $identifier = $cursor->takeName();
        if ($cursor->isSymbol(['.'])) {
            $cursor->advance();
            $source = $cursor->sourceOf($identifier);

            return UpsertExpression::column($source, $cursor->takeName());
        }

        return UpsertExpression::column(UpsertColumnSource::Existing, $identifier);
    }

    /**
     * Reads the comparison operator written here, if one is.
     *
     * Two of PostgreSQL's operators are written as two symbols, and the tokenizer
     * has no reason to have joined them, so both spellings are read here.
     *
     * @param PgSqlUpsertExpressionCursor $cursor Where the reader has got to
     *
     * @return UpsertExpressionKind|null What is being compared, or null where nothing is
     *
     * @throws UnsupportedSqlException When the symbols spell no operator PostgreSQL has
     */
    public function comparisonOperator(PgSqlUpsertExpressionCursor $cursor): ?UpsertExpressionKind
    {
        if (!$cursor->isSymbol(['=', '!', '<', '>'])) {
            return null;
        }
        $token = $cursor->token();
        $next = $cursor->tokenAt(1);
        if ($token === null) {
            return null;
        }
        $operator = $token->text;
        if ($operator !== '=' && $next !== null && $cursor->isSymbolAt(1, ['=', '>'])) {
            $operator .= $next->text;
            $cursor->advance();
        }
        $cursor->advance();

        return match ($operator) {
            '=' => UpsertExpressionKind::Equal,
            '!=', '<>' => UpsertExpressionKind::NotEqual,
            '<' => UpsertExpressionKind::Less,
            '<=' => UpsertExpressionKind::LessOrEqual,
            '>' => UpsertExpressionKind::Greater,
            '>=' => UpsertExpressionKind::GreaterOrEqual,
            default => throw $cursor->unsupported(),
        };
    }
}
