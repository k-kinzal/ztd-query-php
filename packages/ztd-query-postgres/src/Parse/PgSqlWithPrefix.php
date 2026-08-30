<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres\Parse;

use ZtdQuery\Platform\Postgres\Dialect\PgSqlLexerProfile;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * Reads the WITH prefix a statement may begin with.
 *
 * PostgreSQL lets a statement declare common table expressions before the
 * statement itself, and ZTD has to know where one ends and the other begins:
 * to read the statement on its own, to know which names are already taken, and
 * to carry a caller's own prefix across a rewrite. All of that is reading, so
 * it is kept apart from writing a statement back out with shadow tables in it.
 */
final class PgSqlWithPrefix
{
    /**
     * Statement sql.
     *
     * @param string $sql
     * @return string
     */
    public function statementSql(string $sql): string
    {
        $offset = $this->parseHeader($sql)['statementOffset'];

        return $offset === null ? $sql : substr($sql, $offset);
    }

    /**
     * Reads the WITH a statement opens with: what it names, and where the statement itself starts.
     *
     * A header ZTD cannot read all the way through answers the names it did
     * read but no offset, because the statement's own start is then unknown.
     *
     * @param string $sql Statement being read, as written
     *
     * @return array{names: list<string>, statementOffset: int|null} What it answers
     */
    public function parseHeader(string $sql): array
    {
        $tokens = $this->topLevelTokens($sql);
        if (($tokens[0] ?? null)?->isKeyword('WITH') !== true) {
            return ['names' => [], 'statementOffset' => null];
        }

        $index = ($tokens[1] ?? null)?->isKeyword('RECURSIVE') === true ? 2 : 1;
        $names = [];
        foreach ($tokens as $ignored) {
            $name = isset($tokens[$index]) ? $this->identifierName($tokens[$index]) : null;
            if ($name === null) {
                break;
            }
            $body = $this->bodyIndex($tokens, $index + 1);
            if (!$this->isSymbol($tokens[$body] ?? null, '(')
                || !$this->isSymbol($tokens[$body + 1] ?? null, ')')
            ) {
                return ['names' => $names, 'statementOffset' => null];
            }
            $names[] = strtolower($name);
            $index = $body + 2;
            if (!$this->isSymbol($tokens[$index] ?? null, ',')) {
                break;
            }
            $index++;
        }

        return ['names' => $names, 'statementOffset' => ($tokens[$index] ?? null)?->offset];
    }

    /**
     * Answers the tokens of the statement itself, leaving out what it nests.
     *
     * @param string $sql Statement being read, as written
     *
     * @return list<SqlToken> The tokens written inside no parenthesis
     */
    public function topLevelTokens(string $sql): array
    {
        $tokens = [];
        foreach (SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create())->significantTokens() as $token) {
            if ($token->isTopLevel()) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    /**
     * Answers where the body of one WITH entry begins.
     *
     * What is written between the name and the body is the column list, the
     * AS, and whichever of NOT and MATERIALIZED the entry declares.
     *
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param int $index Where the name left off
     *
     * @return int Where the body begins
     */
    public function bodyIndex(array $tokens, int $index): int
    {
        $index = ($this->findAsIndex($tokens, $index) ?? count($tokens)) + 1;
        if (($tokens[$index] ?? null)?->isKeyword('NOT') === true) {
            $index++;
        }
        if (($tokens[$index] ?? null)?->isKeyword('MATERIALIZED') === true) {
            $index++;
        }

        return $index;
    }

    /**
     * @return list<string>
     */
    public function declaredCteNames(string $sql): array
    {
        return $this->parseHeader($sql)['names'];
    }

    /**
     * Carry prefix.
     *
     * @param string $originalSql
     * @param string $rewrittenStatement
     * @return string
     */
    public function carryPrefix(string $originalSql, string $rewrittenStatement): string
    {
        $header = $this->parseHeader($originalSql);
        if ($header['statementOffset'] === null) {
            return $rewrittenStatement;
        }

        $prefix = rtrim(substr($originalSql, 0, $header['statementOffset']));
        $rewritten = $this->prefixOf($rewrittenStatement);
        if ($rewritten === null) {
            return $prefix . "\n" . $rewrittenStatement;
        }
        if ($this->referencesAnyIdentifier($rewritten['body'], $header['names'])) {
            return $prefix . ",\n" . $rewritten['body'] . "\n" . $rewritten['tail'];
        }

        $original = $this->prefixOf($originalSql);
        if ($original === null) {
            return $prefix . "\n" . $rewrittenStatement;
        }

        return $original['leading']
            . 'WITH '
            . ($original['recursive'] ? 'RECURSIVE ' : '')
            . $rewritten['body']
            . ",\n"
            . $original['body']
            . "\n"
            . $rewritten['tail'];
    }

    /**
     * Answers the WITH prefix a statement opens with, taken apart.
     *
     * @param string $sql Statement being read, as written
     *
     * @return array{leading: string, recursive: bool, body: string, tail: string}|null What is written before the prefix, whether it recurses, the entries it declares and the statement they lead to, or null where the statement opens with no prefix ZTD can read
     */
    public function prefixOf(string $sql): ?array
    {
        $tokens = SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create())->significantTokens();
        $with = $tokens[0] ?? null;
        if ($with === null || !$with->isKeyword('WITH')) {
            return null;
        }
        $statementOffset = $this->parseHeader($sql)['statementOffset'];
        if ($statementOffset === null) {
            return null;
        }

        $next = $tokens[1] ?? null;
        $content = $next !== null && $next->isKeyword('RECURSIVE') ? $next : $with;
        $recursive = $content !== $with;

        return [
            'leading' => substr($sql, 0, $with->offset),
            'recursive' => $recursive,
            'body' => trim(substr($sql, $content->endOffset(), $statementOffset - $content->endOffset())),
            'tail' => substr($sql, $statementOffset),
        ];
    }

    /**
     * Answers where the AS of one WITH entry is written.
     *
     * Anything between the name and its AS is the column list, so a bare word
     * there means this is not a WITH entry after all.
     *
     * @param list<SqlToken> $tokens Tokens the statement was read as
     * @param int $start Where to start
     *
     * @return int|null What it answers
     */
    public function findAsIndex(array $tokens, int $start): ?int
    {
        for ($index = $start; isset($tokens[$index]); $index++) {
            $token = $tokens[$index];
            if ($token->isKeyword('AS')) {
                return $index;
            }
            if ($token->kind === SqlTokenKind::Word) {
                return null;
            }
        }

        return null;
    }

    /**
     * Reports whether a token is this symbol.
     *
     * @param SqlToken|null $token Token to read
     * @param string $symbol Symbol it must be
     *
     * @return bool What it answers
     */
    public function isSymbol(?SqlToken $token, string $symbol): bool
    {
        return $token instanceof SqlToken
            && $token->kind === SqlTokenKind::Symbol
            && $token->text === $symbol;
    }

    /**
     * Answers the name a token stands for.
     *
     * @param SqlToken $token Token to read
     *
     * @return string|null What it answers
     */
    public function identifierName(SqlToken $token): ?string
    {
        if ($token->kind === SqlTokenKind::Word) {
            return $token->text;
        }
        if ($token->kind !== SqlTokenKind::QuotedIdentifier || strlen($token->text) < 2) {
            return null;
        }

        $quote = $token->text[0];
        $inner = substr($token->text, 1, -1);

        return str_replace($quote . $quote, $quote, $inner);
    }
    /**
     * Reports whether a statement names something, however it was written.
     *
     * @param string $sql Statement being read, as written
     * @param string $identifier Name, as it was written
     *
     * @return bool What it answers
     */
    public function referencesIdentifier(string $sql, string $identifier): bool
    {
        foreach (SqlTokenStream::tokenize($sql, PgSqlLexerProfile::create())->significantTokens() as $token) {
            $candidate = $this->identifierName($token);
            if ($candidate !== null && strcasecmp($candidate, $identifier) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports whether a statement names any of these.
     *
     * @param string $sql Statement being read, as written
     * @param list<string> $identifiers Names to look for
     *
     * @return bool What it answers
     */
    public function referencesAnyIdentifier(string $sql, array $identifiers): bool
    {
        foreach ($identifiers as $identifier) {
            if ($this->referencesIdentifier($sql, $identifier)) {
                return true;
            }
        }

        return false;
    }
}
