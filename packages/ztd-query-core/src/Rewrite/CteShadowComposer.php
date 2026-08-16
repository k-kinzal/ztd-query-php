<?php

declare(strict_types=1);

namespace ZtdQuery\Rewrite;

use ZtdQuery\Sql\SqlToken;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

final class CteShadowComposer
{
    /**
     * @param array<string, string> $tableCtes
     */
    public function compose(string $sql, array $tableCtes): string
    {
        $declared = array_fill_keys($this->declaredCteNames($sql), true);
        $ctes = [];
        foreach ($tableCtes as $table => $cte) {
            $normalized = strtolower($table);
            if (isset($declared[$normalized]) || !$this->referencesIdentifier($sql, $table)) {
                continue;
            }
            $ctes[] = $cte;
        }

        if ($ctes === []) {
            return $sql;
        }

        $tokens = SqlTokenStream::tokenize($sql)->significantTokens();
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

    /** @return list<string> */
    public function declaredCteNames(string $sql): array
    {
        return $this->parseHeader($sql)['names'];
    }

    public function carryPrefix(string $originalSql, string $rewrittenStatement): string
    {
        $header = $this->parseHeader($originalSql);
        if ($header['statementOffset'] === null) {
            return $rewrittenStatement;
        }

        $prefix = rtrim(substr($originalSql, 0, $header['statementOffset']));

        $rewrittenTokens = SqlTokenStream::tokenize($rewrittenStatement)->significantTokens();
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
            $dependsOnOriginalCte = false;
            foreach ($header['names'] as $name) {
                if ($this->referencesIdentifier($rewrittenBody, $name)) {
                    $dependsOnOriginalCte = true;
                    break;
                }
            }
            if ($dependsOnOriginalCte) {
                return $prefix . ",\n" . $rewrittenBody . "\n" . $rewrittenTail;
            }

            $originalTokens = SqlTokenStream::tokenize($originalSql)->significantTokens();
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

    public function statementSql(string $sql): string
    {
        $offset = $this->parseHeader($sql)['statementOffset'];

        return $offset === null ? $sql : substr($sql, $offset);
    }

    /** @return array{names: list<string>, statementOffset: int|null} */
    private function parseHeader(string $sql): array
    {
        $tokens = SqlTokenStream::tokenize($sql)->significantTokens();
        if (($tokens[0] ?? null)?->isKeyword('WITH') !== true) {
            return ['names' => [], 'statementOffset' => null];
        }

        $index = 1;
        if (($tokens[$index] ?? null)?->isKeyword('RECURSIVE') === true) {
            $index++;
        }

        $names = [];
        while (isset($tokens[$index]) && $tokens[$index]->isTopLevel()) {
            $name = $this->identifierName($tokens[$index]);
            if ($name === null) {
                break;
            }
            $index++;

            while (isset($tokens[$index]) && !$tokens[$index]->isKeyword('AS')) {
                if (!$tokens[$index]->isTopLevel()) {
                    $index++;
                    continue;
                }
                if ($tokens[$index]->kind === SqlTokenKind::Word) {
                    break 2;
                }
                $index++;
            }
            if (($tokens[$index] ?? null)?->isKeyword('AS') !== true) {
                break;
            }
            $names[] = strtolower($name);
            $index++;

            while (isset($tokens[$index]) && !($tokens[$index]->kind === SqlTokenKind::Symbol && $tokens[$index]->text === '(')) {
                $index++;
            }
            if (!isset($tokens[$index])) {
                break;
            }
            $index++;

            while (isset($tokens[$index])) {
                $token = $tokens[$index];
                if ($token->kind === SqlTokenKind::Symbol && $token->text === ')' && $token->isTopLevel()) {
                    $index++;
                    break;
                }
                $index++;
            }

            $separator = $tokens[$index] ?? null;
            if ($separator === null || $separator->kind !== SqlTokenKind::Symbol || $separator->text !== ',' || !$separator->isTopLevel()) {
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

    private function referencesIdentifier(string $sql, string $identifier): bool
    {
        foreach (SqlTokenStream::tokenize($sql)->significantTokens() as $token) {
            $candidate = $this->identifierName($token);
            if ($candidate !== null && strcasecmp($candidate, $identifier) === 0) {
                return true;
            }
        }

        return false;
    }

    private function identifierName(SqlToken $token): ?string
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
