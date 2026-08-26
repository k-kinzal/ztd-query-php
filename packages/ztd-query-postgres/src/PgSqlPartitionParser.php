<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Schema\TablePartitionKey;
use ZtdQuery\Schema\TablePartitionRelation;
use ZtdQuery\Schema\TablePartitionStrategy;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * The pg sql partition parser.
 */
final class PgSqlPartitionParser
{
    /**
     * Reads key.
     *
     * @param string $sql
     * @return ?TablePartitionKey
     */
    public function parseKey(string $sql): ?TablePartitionKey
    {
        $stream = SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();
        $partition = $this->keywordPairIndex($tokens, 'PARTITION', 'BY');
        if ($partition === null) {
            return null;
        }

        $strategyToken = $tokens[$partition + 2] ?? null;
        if (!$strategyToken instanceof SqlToken) {
            return null;
        }
        $strategy = match (true) {
            $strategyToken->isKeyword('RANGE') => TablePartitionStrategy::Range,
            $strategyToken->isKeyword('LIST') => TablePartitionStrategy::List,
            $strategyToken->isKeyword('HASH') => TablePartitionStrategy::Hash,
            default => null,
        };
        if ($strategy === null) {
            return null;
        }

        $openIndex = $partition + 3;
        $closeIndex = $this->closingParenthesisIndex($tokens, $openIndex);
        if ($closeIndex === null) {
            return null;
        }
        $open = $tokens[$openIndex];
        $close = $tokens[$closeIndex];
        $body = substr($sql, $open->endOffset(), $close->offset - $open->endOffset());
        $expressions = SqlTokenStream::tokenize($body, PgSqlLexerProfile::create())->splitTopLevel();
        if ($expressions === [] || in_array('', $expressions, true)) {
            return null;
        }

        return new TablePartitionKey($strategy, $expressions);
    }

    /**
     * Parent table.
     *
     * @param string $sql
     * @return ?string
     */
    public function parentTable(string $sql): ?string
    {
        $stream = SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();
        $partition = $this->keywordPairIndex($tokens, 'PARTITION', 'OF');
        if ($partition === null) {
            return null;
        }

        return $this->qualifiedIdentifierAt($stream, $tokens, $partition + 2)['name'] ?? null;
    }

    /**
     * Reads relation.
     *
     * @param string $sql
     * @param TablePartitionKey $parentKey
     * @return ?TablePartitionRelation
     */
    public function parseRelation(string $sql, TablePartitionKey $parentKey): ?TablePartitionRelation
    {
        $stream = SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create());
        $tokens = $stream->significantTokens();
        $partition = $this->keywordPairIndex($tokens, 'PARTITION', 'OF');
        if ($partition === null) {
            return null;
        }
        $parent = $this->qualifiedIdentifierAt($stream, $tokens, $partition + 2);
        if ($parent === null) {
            return null;
        }

        $defaultIndex = $this->keywordIndex($tokens, 'DEFAULT');
        $valuesIndex = $this->keywordPairIndex($tokens, 'FOR', 'VALUES');
        if ($defaultIndex !== null) {
            return new TablePartitionRelation($parent['name'], null);
        }
        if ($valuesIndex === null) {
            return null;
        }

        $predicate = match ($parentKey->strategy) {
            TablePartitionStrategy::Range => $this->rangePredicate($sql, $tokens, $valuesIndex + 2, $parentKey),
            TablePartitionStrategy::List => $this->listPredicate($sql, $tokens, $valuesIndex + 2, $parentKey),
            TablePartitionStrategy::Hash => null,
        };

        return $predicate === null ? null : new TablePartitionRelation($parent['name'], $predicate);
    }

    /**
     * Answers which rows land in a partition declared by range.
     *
     * @param string $sql Statement being read, as written
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param int $index Where to read
     * @param TablePartitionKey $key The key
     *
     * @return string|null What it answers
     */
    public function rangePredicate(string $sql, array $tokens, int $index, TablePartitionKey $key): ?string
    {
        $from = $tokens[$index] ?? null;
        if (!$from instanceof SqlToken || !$from->isKeyword('FROM')) {
            return null;
        }
        $lower = $this->parenthesizedValues($sql, $tokens, $index + 1);
        if ($lower === null) {
            return null;
        }

        $to = $tokens[$lower['next']] ?? null;
        if (!$to instanceof SqlToken || !$to->isKeyword('TO')) {
            return null;
        }
        $upper = $this->parenthesizedValues($sql, $tokens, $lower['next'] + 1);
        if ($upper === null) {
            return null;
        }

        $lowerPredicate = $this->rangeBoundary($key->expressions, $lower['values'], '>=', 'MINVALUE');
        $upperPredicate = $this->rangeBoundary($key->expressions, $upper['values'], '<', 'MAXVALUE');
        if ($lowerPredicate === false || $upperPredicate === false) {
            return null;
        }

        $predicates = array_filter(
            [$lowerPredicate, $upperPredicate],
            static fn (string|false|null $predicate): bool => is_string($predicate),
        );

        return $predicates === [] ? 'TRUE' : implode(' AND ', $predicates);
    }

    /**
     * Answers which rows land in a partition declared by value.
     *
     * @param string $sql Statement being read, as written
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param int $index Where to read
     * @param TablePartitionKey $key The key
     *
     * @return string|null What it answers
     */
    public function listPredicate(string $sql, array $tokens, int $index, TablePartitionKey $key): ?string
    {
        if (count($key->expressions) !== 1) {
            return null;
        }
        $in = $tokens[$index] ?? null;
        if (!$in instanceof SqlToken || !$in->isKeyword('IN')) {
            return null;
        }
        $values = $this->parenthesizedValues($sql, $tokens, $index + 1);
        if ($values === null) {
            return null;
        }

        $nonNull = [];
        $hasNull = false;
        foreach ($values['values'] as $value) {
            $valueTokens = SqlTokenStream::tokenize($value, PgSqlLexerProfile::create())->significantTokens();
            if (count($valueTokens) === 1 && $valueTokens[0]->isKeyword('NULL')) {
                $hasNull = true;
                continue;
            }
            $nonNull[] = $value;
        }
        $expression = '(' . $key->expressions[0] . ')';
        $predicate = $nonNull === [] ? null : $expression . ' IN (' . implode(', ', $nonNull) . ')';
        if ($hasNull) {
            $nullPredicate = "$expression IS NULL";
            $predicate = $predicate === null ? $nullPredicate : "($predicate OR $nullPredicate)";
        }

        return $predicate;
    }

    /**
     * Answers one end of a range, as written.
     *
     * @param non-empty-list<string> $expressions The expressions
     * @param list<string> $values The values
     * @param string $operator The operator
     * @param string $unbounded The unbounded
     *
     * @return string|false|null What it answers
     */
    public function rangeBoundary(array $expressions, array $values, string $operator, string $unbounded): string|false|null
    {
        if (count($expressions) !== count($values)) {
            return false;
        }

        $special = [];
        foreach ($values as $value) {
            $special[] = in_array(strtoupper($value), ['MINVALUE', 'MAXVALUE'], true);
        }
        if (!in_array(false, $special, true)) {
            foreach ($values as $value) {
                if (strcasecmp($value, $unbounded) !== 0) {
                    return false;
                }
            }

            return null;
        }
        if (in_array(true, $special, true)) {
            return false;
        }

        if (count($expressions) === 1) {
            return '(' . $expressions[0] . ") $operator " . $values[0];
        }

        return 'ROW(' . implode(', ', $expressions) . ") $operator ROW(" . implode(', ', $values) . ')';
    }

    /**
     * Answers the values written inside one pair of parentheses.
     *
     * @param string $sql Statement being read, as written
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param int $openIndex The open index
     *
     * @return array{values: list<string>, next: int}|null What it answers
     */
    public function parenthesizedValues(string $sql, array $tokens, int $openIndex): ?array
    {
        $closeIndex = $this->closingParenthesisIndex($tokens, $openIndex);
        if ($closeIndex === null) {
            return null;
        }
        $open = $tokens[$openIndex];
        $close = $tokens[$closeIndex];
        $body = substr($sql, $open->endOffset(), $close->offset - $open->endOffset());
        $values = SqlTokenStream::tokenize($body, PgSqlLexerProfile::create())->splitTopLevel();

        return ['values' => $values, 'next' => $closeIndex + 1];
    }

    /**
     * Answers where the parenthesis that closes this one is written.
     *
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param int $openIndex The open index
     *
     * @return int|null What it answers
     */
    public function closingParenthesisIndex(array $tokens, int $openIndex): ?int
    {
        $open = $tokens[$openIndex] ?? null;
        if (!$open instanceof SqlToken) {
            return null;
        }
        if (!$this->isSymbol($open, '(')) {
            return null;
        }

        $afterOpen = false;
        foreach ($tokens as $index => $token) {
            if ($token === $open) {
                $afterOpen = true;
            } elseif ($afterOpen && $this->isSymbol($token, ')') && $token->depth === $open->depth) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Answers where a pair of keywords is written together.
     *
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param string $first The first
     * @param string $second The second
     *
     * @return int|null What it answers
     */
    public function keywordPairIndex(array $tokens, string $first, string $second): ?int
    {
        foreach ($tokens as $index => $token) {
            if (!$token->isTopLevel()) {
                continue;
            }
            if (!$token->isKeyword($first)) {
                continue;
            }
            $next = $tokens[$index + 1] ?? null;
            if (!$next instanceof SqlToken) {
                continue;
            }
            if (!$next->isKeyword($second)) {
                continue;
            }

            return $index;
        }

        return null;
    }

    /**
     * Answers where a keyword is written.
     *
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param string $keyword Keyword to look for
     *
     * @return int|null What it answers
     */
    public function keywordIndex(array $tokens, string $keyword): ?int
    {
        foreach ($tokens as $index => $token) {
            if ($token->isTopLevel() && $token->isKeyword($keyword)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Reads a name written here, however many parts it has.
     *
     * @param SqlTokenStream $stream Stream to read
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param int $index Where to read
     *
     * @return array{name: string, next: int}|null What it answers
     */
    public function qualifiedIdentifierAt(SqlTokenStream $stream, array $tokens, int $index): ?array
    {
        $token = $tokens[$index] ?? null;
        if (!$token instanceof SqlToken) {
            return null;
        }
        if (!in_array($token->kind, [SqlTokenKind::Word, SqlTokenKind::QuotedIdentifier], true)) {
            return null;
        }
        $identifier = $stream->identifierAt($index);
        if ($identifier === null) {
            return null;
        }
        if ($token->kind === SqlTokenKind::Word) {
            $identifier['name'] = strtolower($identifier['name']);
        }

        $dot = $tokens[$identifier['next']] ?? null;
        while ($dot instanceof SqlToken && $this->isSymbol($dot, '.')) {
            $componentIndex = $identifier['next'] + 1;
            $component = $this->qualifiedIdentifierAt($stream, $tokens, $componentIndex);
            if ($component === null) {
                return null;
            }
            $identifier = $component;
            $dot = $tokens[$identifier['next']] ?? null;
        }

        return $identifier;
    }

    /**
     * Reports whether a token is this symbol.
     *
     * @param SqlToken $token Token to read
     * @param string $symbol Symbol it must be
     *
     * @return bool What it answers
     */
    public function isSymbol(SqlToken $token, string $symbol): bool
    {
        return $token->kind === SqlTokenKind::Symbol && $token->text === $symbol;
    }
}
