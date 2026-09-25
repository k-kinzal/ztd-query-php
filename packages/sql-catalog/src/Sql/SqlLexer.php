<?php

declare(strict_types=1);

namespace SqlCatalog\Sql;

/**
 * Splits SQL into tokens without committing to one dialect's grammar.
 *
 * Only enough structure is recognised to tell keywords, string bodies, comments
 * and bind parameters apart, which is what reading a statement's kind, its
 * tables and its placeholders needs. Anything unrecognised becomes a symbol.
 *
 * @visibility root
 */
final class SqlLexer
{
    private const QUOTES = ["'" => "'", '"' => '"', '`' => '`', '[' => ']'];

    /**
     * The tokens of a statement, in order.
     *
     * @return list<SqlToken>
     */
    public function tokenize(string $sql): array
    {
        $tokens = [];
        $length = strlen($sql);
        $offset = 0;
        $depth = 0;

        while ($offset < $length) {
            $character = $sql[$offset];
            if (ctype_space($character)) {
                $offset++;
                continue;
            }
            if ($character === '(' || $character === ')') {
                $depth = $character === '(' ? $depth + 1 : max(0, $depth - 1);
                $tokens[] = new SqlToken(SqlTokenKind::Symbol, $character, $offset, $depth);
                $offset++;
                continue;
            }

            $token = $this->readToken($sql, $offset, $depth);
            $tokens[] = $token;
            $offset += max(1, strlen($token->text));
        }

        return $tokens;
    }

    /**
     * The token starting at the given offset.
     */
    public function readToken(string $sql, int $offset, int $depth): SqlToken
    {
        $comment = $this->readComment($sql, $offset);
        if ($comment !== null) {
            return new SqlToken(SqlTokenKind::Comment, $comment, $offset, $depth);
        }

        $quoted = $this->readQuoted($sql, $offset);
        if ($quoted !== null) {
            $kind = $sql[$offset] === "'" ? SqlTokenKind::Text : SqlTokenKind::Identifier;

            return new SqlToken($kind, $quoted, $offset, $depth);
        }

        if ($sql[$offset] === ':' && ($sql[$offset + 1] ?? '') === ':') {
            return new SqlToken(SqlTokenKind::Symbol, '::', $offset, $depth);
        }

        $parameter = $this->readParameter($sql, $offset);
        if ($parameter !== null) {
            return new SqlToken(SqlTokenKind::Parameter, $parameter, $offset, $depth);
        }

        if (preg_match('/\G\d+(?:\.\d+)?/', $sql, $matches, 0, $offset) === 1) {
            return new SqlToken(SqlTokenKind::Number, $matches[0], $offset, $depth);
        }
        if (preg_match('/\G[A-Za-z_\x80-\xff][A-Za-z0-9_$\x80-\xff]*/', $sql, $matches, 0, $offset) === 1) {
            return new SqlToken(SqlTokenKind::Word, $matches[0], $offset, $depth);
        }

        return new SqlToken(SqlTokenKind::Symbol, $sql[$offset], $offset, $depth);
    }

    /**
     * The comment starting at the offset, or null when none starts there.
     */
    public function readComment(string $sql, int $offset): ?string
    {
        if (preg_match('/\G(?:--|#)[^\n]*/', $sql, $matches, 0, $offset) === 1) {
            return $matches[0];
        }
        if (preg_match('/\G\/\*.*?(?:\*\/|$)/s', $sql, $matches, 0, $offset) === 1) {
            return $matches[0];
        }

        return null;
    }

    /**
     * The quoted run starting at the offset, or null when none starts there.
     */
    public function readQuoted(string $sql, int $offset): ?string
    {
        $open = $sql[$offset];
        $close = self::QUOTES[$open] ?? null;
        if ($close === null) {
            return null;
        }

        $length = strlen($sql);
        $cursor = $offset + 1;
        while ($cursor < $length) {
            if ($sql[$cursor] === '\\' && $open === "'") {
                $cursor += 2;
                continue;
            }
            if ($sql[$cursor] === $close) {
                $doubled = ($sql[$cursor + 1] ?? '') === $close;
                $cursor += $doubled ? 2 : 1;
                if (!$doubled) {
                    return substr($sql, $offset, $cursor - $offset);
                }
                continue;
            }
            $cursor++;
        }

        return substr($sql, $offset);
    }

    /**
     * The bind parameter starting at the offset, or null when none starts there.
     */
    public function readParameter(string $sql, int $offset): ?string
    {
        if ($sql[$offset] === '?') {
            return preg_match('/\G\?\d+/', $sql, $matches, 0, $offset) === 1 ? $matches[0] : '?';
        }
        if (preg_match('/\G:[A-Za-z_][A-Za-z0-9_]*/', $sql, $matches, 0, $offset) === 1) {
            return $matches[0];
        }
        if (preg_match('/\G\$\d+/', $sql, $matches, 0, $offset) === 1) {
            return $matches[0];
        }

        return null;
    }
}
