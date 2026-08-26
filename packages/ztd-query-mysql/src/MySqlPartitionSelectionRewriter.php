<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Rewrite\SqlTransformer;
use ZtdQuery\Schema\TablePartitioning;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Rewrites explicit MySQL partition selection into filtered table sources.
 *
 * @phpstan-import-type ShadowTables from SqlTransformer
 */
final class MySqlPartitionSelectionRewriter
{
    /**
     * @param ShadowTables $tables Table name => what the shadow holds for it
     *
     * @throws UnsupportedSqlException
     */
    public function rewrite(string $sql, array $tables): string
    {
        $stream = SqlTokenStream::tokenize($sql, MySqlLexerProfile::create());
        $tokens = $stream->significantTokens();
        $contexts = [];
        foreach ($tables as $table => $context) {
            $contexts[strtolower($table)] = $context;
        }

        $edits = [];
        foreach ((new MySqlSelectRelationParser())->references($sql) as $reference) {
            $partitionIndex = $this->tokenIndexAtOrAfter($tokens, $reference['end']);
            $partition = $tokens[$partitionIndex] ?? null;
            if (!$partition instanceof SqlToken || !$partition->isKeyword('PARTITION')) {
                continue;
            }

            $open = $tokens[$partitionIndex + 1] ?? null;
            if (!$open instanceof SqlToken || !$this->isSymbol($open, '(')) {
                throw new UnsupportedSqlException($sql, 'PARTITION selection opening parenthesis');
            }
            $closeIndex = $this->closingParenthesisIndex($tokens, $open);
            if ($closeIndex === null) {
                throw new UnsupportedSqlException($sql, 'PARTITION selection closing parenthesis');
            }

            $names = $this->partitionNames($sql, $open, $tokens[$closeIndex]);
            $context = $contexts[strtolower($reference['name'])] ?? null;
            $partitioning = $context['partitioning'] ?? null;
            $predicates = $partitioning instanceof TablePartitioning
                ? $partitioning->predicatesFor($names)
                : null;
            if ($predicates === null) {
                throw new UnsupportedSqlException($sql, 'PARTITION selection');
            }
            $predicate = implode(' OR ', array_map(
                static fn (string $partitionPredicate): string => "($partitionPredicate)",
                $predicates,
            ));

            $after = $tokens[$closeIndex + 1] ?? null;
            if ($after instanceof SqlToken
                && in_array(strtoupper($after->text), ['USE', 'FORCE', 'IGNORE'], true)
            ) {
                throw new UnsupportedSqlException($sql, 'PARTITION selection with index hint');
            }

            $tableSql = substr(
                $sql,
                $reference['unqualifiedStart'],
                $reference['end'] - $reference['unqualifiedStart'],
            );
            $replacement = "(SELECT * FROM $tableSql WHERE $predicate)";
            if (!$this->hasAlias($tokens, $closeIndex + 1)) {
                $replacement .= " AS $tableSql";
            }
            $edits[] = [
                'start' => $reference['start'],
                'end' => $tokens[$closeIndex]->endOffset(),
                'replacement' => $replacement,
            ];
        }

        usort($edits, static fn (array $left, array $right): int => $right['start'] <=> $left['start']);
        foreach ($edits as $edit) {
            $sql = substr_replace($sql, $edit['replacement'], $edit['start'], $edit['end'] - $edit['start']);
        }

        return $sql;
    }

    /** @param list<SqlToken> $tokens */
    /**
     * Answers which token comes after the one ending here.
     *
     * @param list<SqlToken> $tokens The statement, as tokens
     * @param int $offset Where the token before it ends
     *
     * @return int Where the next token is, or one past the end when there is none
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
     * Answers where the parenthesis that closes this one is written.
     *
     * @param list<SqlToken> $tokens The statement, as tokens
     * @param SqlToken $open The opening parenthesis
     *
     * @return int|null Where it closes, or null where it never does
     */
    public function closingParenthesisIndex(array $tokens, SqlToken $open): ?int
    {
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
     * Answers the partitions a PARTITION clause names.
     *
     * @param string $sql Statement being rewritten
     * @param SqlToken $open Where the clause's parentheses open
     * @param SqlToken $close Where they close
     *
     * @return non-empty-list<string> The partitions named
     *
     * @throws UnsupportedSqlException When the clause names anything but plain partition names
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
     * Reports whether the statement gave the table a name of its own here.
     *
     * A word here may be an alias or may open the next clause, and only the
     * words that cannot open one are aliases.
     *
     * @param list<SqlToken> $tokens The statement, as tokens
     * @param int $index Where to look, just past the table name
     *
     * @return bool True when the word there is an alias
     */
    public function hasAlias(array $tokens, int $index): bool
    {
        $token = $tokens[$index] ?? null;
        if ($token === null) {
            return false;
        }
        if (!$this->isIdentifier($token)) {
            return false;
        }

        return !in_array(
            strtoupper($token->text),
            ['JOIN', 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'CROSS', 'STRAIGHT_JOIN', 'WHERE', 'GROUP', 'HAVING', 'ORDER', 'LIMIT', 'OFFSET', 'UNION', 'INTERSECT', 'EXCEPT', 'FOR', 'LOCK', 'ON', 'USING'],
            true,
        );
    }

    /**
     * Reports whether a token is a name at all.
     *
     * @param SqlToken $token Token to test
     *
     * @return bool True for a bare word or a quoted name
     */
    public function isIdentifier(SqlToken $token): bool
    {
        return in_array($token->kind, [SqlTokenKind::Word, SqlTokenKind::QuotedIdentifier], true);
    }

    /**
     * Reports whether a token is this symbol.
     *
     * @param SqlToken $token Token to test
     * @param string $symbol Symbol it must be
     *
     * @return bool True when it is
     */
    public function isSymbol(SqlToken $token, string $symbol): bool
    {
        return $token->kind === SqlTokenKind::Symbol && $token->text === $symbol;
    }
}
