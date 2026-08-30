<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Sqlite\Rewrite;

use ZtdQuery\Platform\Sqlite\Dialect\SqliteLexerProfile;
use ZtdQuery\Platform\Sqlite\Parse\SqliteSelectRelationParser;
use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

/**
 * The sqlite cte shadow composer.
 */
final class SqliteCteShadowComposer
{
    /**
     * @param array<string, string> $tableCtes
     */
    public function compose(string $sql, array $tableCtes): string
    {
        $declared = array_fill_keys($this->declaredCteNames($sql), true);
        $requiredSql = [$sql];
        $requiredCtes = [];
        foreach (array_reverse($tableCtes, true) as $table => $cte) {
            $normalized = strtolower($table);
            if (isset($declared[$normalized])) {
                continue;
            }
            $referenced = false;
            foreach ($requiredSql as $requiredPart) {
                if ($this->referencesIdentifier($requiredPart, $table)) {
                    $referenced = true;
                }
            }
            if (!$referenced) {
                continue;
            }
            $requiredCtes[$table] = $cte;
            $requiredSql[] = $cte;
        }

        $requiredCtes = array_reverse($requiredCtes, true);
        $ctes = $requiredCtes;
        $shadowedTables = array_keys($requiredCtes);

        if ($ctes === []) {
            return $sql;
        }

        $sql = (new SqliteSelectRelationParser())->unqualify($sql, $shadowedTables);
        $tokens = SqlTokenStream::tokenize($sql, SqliteLexerProfile::create())->significantTokens();
        $with = $tokens[0] ?? null;
        if ($with === null || !$with->isKeyword('WITH')) {
            return 'WITH ' . implode(",\n", $ctes) . "\n" . $sql;
        }

        $insertionToken = $with;
        $next = $tokens[1] ?? null;
        if ($next !== null && $next->isTopLevel() && $next->isKeyword('RECURSIVE')) {
            $insertionToken = $next;
        }

        return substr_replace(
            $sql,
            ' ' . implode(",\n", $ctes) . ",\n",
            $insertionToken->endOffset(),
            0,
        );
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

        $rewrittenTokens = SqlTokenStream::tokenize($rewrittenStatement, SqliteLexerProfile::create())->significantTokens();
        $rewrittenWith = $rewrittenTokens[0] ?? null;
        if ($rewrittenWith !== null && $rewrittenWith->isKeyword('WITH')) {
            $rewrittenHeader = $this->parseHeader($rewrittenStatement);
            $rewrittenStatementOffset = $rewrittenHeader['statementOffset'];
            if ($rewrittenStatementOffset === null) {
                return $prefix . "\n" . $rewrittenStatement;
            }

            $contentToken = $rewrittenWith;
            $rewrittenNext = $rewrittenTokens[1] ?? null;
            if ($rewrittenNext !== null && $rewrittenNext->isKeyword('RECURSIVE')) {
                $contentToken = $rewrittenNext;
            }

            $rewrittenBody = trim(substr(
                $rewrittenStatement,
                $contentToken->endOffset(),
                $rewrittenStatementOffset - $contentToken->endOffset(),
            ));
            $rewrittenTail = substr($rewrittenStatement, $rewrittenStatementOffset);
            if ($this->referencesAnyIdentifier($rewrittenBody, $header['names'])) {
                return $prefix . ",\n" . $rewrittenBody . "\n" . $rewrittenTail;
            }

            $originalTokens = SqlTokenStream::tokenize($originalSql, SqliteLexerProfile::create())->significantTokens();
            $originalWith = $originalTokens[0];
            $originalContentToken = $originalWith;
            $recursive = false;
            $originalNext = $originalTokens[1] ?? null;
            if ($originalNext !== null && $originalNext->isKeyword('RECURSIVE')) {
                $originalContentToken = $originalNext;
                $recursive = true;
            }
            $originalBody = trim(substr(
                $originalSql,
                $originalContentToken->endOffset(),
                $header['statementOffset'] - $originalContentToken->endOffset(),
            ));
            $leading = substr($originalSql, 0, $originalWith->offset);

            return $leading
                . 'WITH '
                . ($recursive ? 'RECURSIVE ' : '')
                . $rewrittenBody
                . ",\n"
                . $originalBody
                . "\n"
                . $rewrittenTail;
        }

        return $prefix . "\n" . $rewrittenStatement;
    }

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
     * @param string $sql Statement being read, as written
     *
     * @return array{names: list<string>, statementOffset: int|null} What it answers
     */
    public function parseHeader(string $sql): array
    {
        $tokens = [];
        foreach (SqlTokenStream::tokenize($sql, SqliteLexerProfile::create())->significantTokens() as $token) {
            if ($token->isTopLevel()) {
                $tokens[] = $token;
            }
        }
        if (($tokens[0] ?? null)?->isKeyword('WITH') !== true) {
            return ['names' => [], 'statementOffset' => null];
        }

        $index = 1;
        if (($tokens[$index] ?? null)?->isKeyword('RECURSIVE') === true) {
            $index++;
        }

        $names = [];
        while (isset($tokens[$index])) {
            $name = $this->identifierName($tokens[$index]);
            if ($name === null) {
                break;
            }
            $index++;

            $asIndex = $this->findAsIndex($tokens, $index);
            $index = ($asIndex ?? count($tokens)) + 1;

            if (($tokens[$index] ?? null)?->isKeyword('NOT') === true) {
                $index++;
            }
            if (($tokens[$index] ?? null)?->isKeyword('MATERIALIZED') === true) {
                $index++;
            }

            if (!$this->isSymbol($tokens[$index] ?? null, '(')
                || !$this->isSymbol($tokens[$index + 1] ?? null, ')')
            ) {
                return ['names' => $names, 'statementOffset' => null];
            }
            $names[] = strtolower($name);
            $index += 2;

            $separator = $tokens[$index] ?? null;
            if (!$this->isSymbol($separator, ',')) {
                break;
            }
            $index++;
        }

        $statement = $tokens[$index] ?? null;

        return [
            'names' => $names,
            'statementOffset' => $statement?->offset,
        ];
    }

    /**
     * Answers where the AS of one WITH entry is written.
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
     * Reports whether a statement names something, however it was written.
     *
     * @param string $sql Statement being read, as written
     * @param string $identifier Name, as it was written
     *
     * @return bool What it answers
     */
    public function referencesIdentifier(string $sql, string $identifier): bool
    {
        foreach (SqlTokenStream::tokenize($sql, SqliteLexerProfile::create())->significantTokens() as $token) {
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
}
