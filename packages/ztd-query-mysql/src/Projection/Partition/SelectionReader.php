<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Projection\Partition;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Selection Reader.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class SelectionReader
{
    /**
     * @param list<SqlToken> $tokens
     */
    public function tokenIndexAtOrAfter(array $tokens, int $offset): int
    {
        $afterReference = false;
        foreach ($tokens as $index => $token) {
            if ($afterReference) {
                return $index;
            }
            $afterReference = $token->endOffset() === $offset;
        }

        return count($tokens);
    }

    /**
     * @param list<SqlToken> $tokens
     */
    public function closingParenthesisIndex(array $tokens, SqlToken $open): ?int
    {
        $afterOpen = false;
        foreach ($tokens as $index => $token) {
            if ($token === $open) {
                $afterOpen = true;
            } elseif ($afterOpen && ($token->kind === SqlTokenKind::Symbol && $token->text === ')') && $token->depth === $open->depth) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @return non-empty-list<string>
     * @throws UnsupportedSqlException
     */
    public function partitionNames(string $sql, SqlToken $open, SqlToken $close): array
    {
        $list = substr($sql, $open->endOffset(), $close->offset - $open->endOffset());
        $names = [];
        foreach (SqlTokenStream::tokenize($list, MySqlLexerProfile::create())->splitTopLevel() as $part) {
            $stream = SqlTokenStream::tokenize($part, MySqlLexerProfile::create());
            $identifier = $stream->identifierAt();
            if ($identifier === null) {
                throw new UnsupportedSqlException($sql, 'PARTITION selection');
            }
            if ($identifier['next'] !== count($stream->significantTokens())) {
                throw new UnsupportedSqlException($sql, 'PARTITION selection');
            }
            $names[] = $identifier['name'];
        }
        if ($names === []) {
            throw new UnsupportedSqlException($sql, 'PARTITION selection');
        }

        return $names;
    }

    /**
     * @param list<SqlToken> $tokens
     */
    public function hasAlias(array $tokens, int $index): bool
    {
        $token = $tokens[$index] ?? null;
        if ($token === null) {
            return false;
        }
        if (!(in_array($token->kind, [SqlTokenKind::Word, SqlTokenKind::QuotedIdentifier], true))) {
            return false;
        }

        return !in_array(
            strtoupper($token->text),
            ['JOIN', 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'CROSS', 'STRAIGHT_JOIN', 'WHERE', 'GROUP', 'HAVING', 'ORDER', 'LIMIT', 'OFFSET', 'UNION', 'INTERSECT', 'EXCEPT', 'FOR', 'LOCK', 'ON', 'USING'],
            true,
        );
    }
    /**
     * @param list<SqlToken> $tokens
     * @return array{names: non-empty-list<string>, closeIndex: int}
     * @throws UnsupportedSqlException
     */
    public function partitionSelection(string $sql, array $tokens, int $partitionIndex): array
    {
        $open = $tokens[$partitionIndex + 1] ?? null;
        if (!$open instanceof SqlToken || !($open->kind === SqlTokenKind::Symbol && $open->text === '(')) {
            throw new UnsupportedSqlException($sql, 'PARTITION selection opening parenthesis');
        }
        $closeIndex = $this->closingParenthesisIndex($tokens, $open);
        if ($closeIndex === null) {
            throw new UnsupportedSqlException($sql, 'PARTITION selection closing parenthesis');
        }

        $names = $this->partitionNames($sql, $open, $tokens[$closeIndex]);
        return ['names' => $names, 'closeIndex' => $closeIndex];
    }

    /**
     * Reject index hints after partition validation, retaining rejection precedence.
     *
     * @param list<SqlToken> $tokens
     * @throws UnsupportedSqlException
     */
    public function requireNoIndexHint(string $sql, array $tokens, int $closeIndex): void
    {
        $after = $tokens[$closeIndex + 1] ?? null;
        if ($after instanceof SqlToken
            && in_array(strtoupper($after->text), ['USE', 'FORCE', 'IGNORE'], true)
        ) {
            throw new UnsupportedSqlException($sql, 'PARTITION selection with index hint');
        }

    }
}
