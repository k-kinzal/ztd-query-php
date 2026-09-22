<?php

declare(strict_types=1);

namespace SqlCatalog\Sql;

use SqlCatalog\Text\TextPattern;

/**
 * Reads the tables a statement names.
 *
 * The names let a catalog be grouped and filtered by table, and are what a
 * fixture generator needs in order to build rows for the statement. A name
 * the analyzer knows only part of — a prefix read from configuration and a
 * suffix written in the source — is still a name to group by, and is
 * reported with the unknown part marked. A name nothing is known of is not
 * guessed at.
 *
 * @visibility root
 */
final class TableReader
{
    /**
     * The marker an unknown part of a name is reported with.
     */
    public const GAP = '{$}';

    private const INTRODUCERS = ['FROM', 'JOIN', 'INTO', 'UPDATE', 'TABLE'];

    /**
     * The words that can follow an introducer without naming a table.
     */
    private const SKIPPED = ['IF', 'NOT', 'EXISTS'];

    /**
     * The words that are never a table name, however they follow an introducer.
     */
    private const NEVER_NAMES = ['SELECT', 'ONLY', 'LATERAL', 'WHERE', 'SET', 'VALUES', 'ON', 'STATUS', 'KEY', 'DUPLICATE', 'INDEX', 'JOIN', 'IF', 'NOT', 'EXISTS'];

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
        $previous = null;
        foreach ($tokens as $index => $token) {
            $introducer = $token->kind === SqlTokenKind::Word && in_array($token->keyword(), self::INTRODUCERS, true);
            if ($introducer && !($token->keyword() === 'UPDATE' && $previous === 'KEY')) {
                $name = $this->readName(array_slice($tokens, $index + 1));
                if ($name !== null) {
                    $found[str_replace(PlaceholderScanner::HOLE_MARKER, self::GAP, $name)] = true;
                }
            }
            $previous = $token->kind === SqlTokenKind::Word ? $token->keyword() : null;
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
            if ($parts === [] && $token->kind === SqlTokenKind::Word && in_array($token->keyword(), self::SKIPPED, true)) {
                continue;
            }
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
     *
     * A gap on its own is not a name, quoted or not; a word with a gap in it
     * is, since the rest of the word is known.
     */
    public function isName(SqlToken $token): bool
    {
        if ($token->kind === SqlTokenKind::Identifier) {
            return $this->unquote($token) !== PlaceholderScanner::HOLE_MARKER;
        }

        return $token->kind === SqlTokenKind::Word
            && $token->text !== PlaceholderScanner::HOLE_MARKER
            && !in_array($token->keyword(), self::NEVER_NAMES, true);
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
