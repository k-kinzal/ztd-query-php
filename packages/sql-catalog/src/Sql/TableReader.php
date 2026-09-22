<?php

declare(strict_types=1);

namespace SqlCatalog\Sql;

use SqlCatalog\Text\TextPattern;

/**
 * Reads the tables a statement names.
 *
 * The names let a catalog be grouped and filtered by table, and are what a
 * fixture generator needs in order to build rows for the statement.
 *
 * @visibility root
 */
final class TableReader
{
    private const INTRODUCERS = ['FROM', 'JOIN', 'INTO', 'UPDATE', 'TABLE'];

    private SqlLexer $lexer;

    /**
     * Builds a reader over the shared lexer.
     */
    public function __construct(?SqlLexer $lexer = null)
    {
        $this->lexer = $lexer ?? new SqlLexer();
    }

    /**
     * The tables the statement names, in order and without repeats.
     *
     * @return list<string>
     */
    public function read(TextPattern $pattern): array
    {
        $tokens = $this->lexer->tokenize($pattern->render(PlaceholderScanner::HOLE_MARKER));
        $found = [];
        foreach ($tokens as $index => $token) {
            if ($token->kind !== SqlTokenKind::Word || !in_array($token->keyword(), self::INTRODUCERS, true)) {
                continue;
            }
            $name = $this->readName(array_slice($tokens, $index + 1));
            if ($name !== null) {
                $found[$name] = true;
            }
        }

        return array_keys($found);
    }

    /**
     * The possibly qualified table name the tokens start with, or null when they name none.
     *
     * @param list<SqlToken> $tokens
     */
    public function readName(array $tokens): ?string
    {
        $parts = [];
        foreach ($tokens as $token) {
            $expectsName = count($parts) % 2 === 0;
            if ($expectsName && $this->isName($token)) {
                $parts[] = $this->unquote($token);
                continue;
            }
            if (!$expectsName && $token->kind === SqlTokenKind::Symbol && $token->text === '.') {
                $parts[] = '.';
                continue;
            }
            break;
        }

        if ($parts === [] || $parts[count($parts) - 1] === '.') {
            return $parts === [] ? null : implode('', array_slice($parts, 0, -1));
        }

        return implode('', $parts);
    }

    /**
     * Whether the token can start a table name.
     */
    public function isName(SqlToken $token): bool
    {
        if ($token->kind === SqlTokenKind::Identifier) {
            return true;
        }

        return $token->kind === SqlTokenKind::Word
            && $token->text !== PlaceholderScanner::HOLE_MARKER
            && !in_array($token->keyword(), ['SELECT', 'ONLY', 'LATERAL'], true);
    }

    /**
     * The token with any identifier quoting removed.
     */
    public function unquote(SqlToken $token): string
    {
        if ($token->kind !== SqlTokenKind::Identifier) {
            return $token->text;
        }

        return substr($token->text, 1, max(0, strlen($token->text) - 2));
    }
}
