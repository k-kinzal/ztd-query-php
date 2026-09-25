<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Sql;

use SqlCatalog\Core\Text\TextPattern;

/**
 * Finds the bind parameters of a statement, including one still holding gaps.
 *
 * @visibility root
 */
final class PlaceholderScanner
{
    /**
     * The stand-in gaps are rendered as, chosen to lex as one ordinary word.
     */
    public const HOLE_MARKER = 'sql_catalog_gap';

    private SqlLexer $lexer;

    /**
     * Builds a scanner over the shared lexer.
     */
    public function __construct(?SqlLexer $lexer = null)
    {
        $this->lexer = $lexer ?? new SqlLexer();
    }

    /**
     * The bind parameters of a statement, in the order they are written.
     *
     * @return list<PlaceholderRef>
     */
    public function scan(TextPattern $pattern): array
    {
        $placeholders = [];
        $position = 0;
        foreach ($this->lexer->tokenize($pattern->render(self::HOLE_MARKER)) as $token) {
            if ($token->kind !== SqlTokenKind::Parameter) {
                continue;
            }
            $placeholders[] = new PlaceholderRef($token->text, $position, $this->nameOf($token->text));
            $position++;
        }

        return $placeholders;
    }

    /**
     * The name a parameter binds by, or null when it binds by position.
     */
    public function nameOf(string $token): ?string
    {
        if (str_starts_with($token, ':')) {
            return substr($token, 1);
        }
        if (str_starts_with($token, '$')) {
            return substr($token, 1);
        }

        return $token === '?' ? null : ltrim($token, '?');
    }
}
