<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Lexeme;

use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator;

/**
 * Identifier domains explicitly recognized by gram.y semantic actions in PostgreSQL 17.2.
 */
final class ContextualNameDefinitions
{
    /**
     * Composes each source-defined domain after its grammar position has been identified by rewriting.
     */
    public function create(): LexemeGenerator
    {
        return new ChoiceLexemeGenerator(
            $this->domain('POLICY_MODE', ['PERMISSIVE', 'RESTRICTIVE'], 'RowSecurityDefaultPermissive'),
            $this->domain('ROLE_OPTION', [
                'SUPERUSER', 'NOSUPERUSER', 'CREATEROLE', 'NOCREATEROLE',
                'REPLICATION', 'NOREPLICATION', 'CREATEDB', 'NOCREATEDB',
                'LOGIN', 'NOLOGIN', 'BYPASSRLS', 'NOBYPASSRLS', 'NOINHERIT',
            ], 'AlterOptRoleElem'),
        );
    }

    /**
     * Keeps the complete declared word domain in one composable handler.
     * @param non-empty-list<string> $words
     */
    public function domain(string $terminal, array $words, string $rule): LexemeGenerator
    {
        $pattern = implode('|', array_map(static fn (string $word): string => preg_quote($word, '~'), $words));
        return new PatternLexemeGenerator($terminal, '~\A(?:' . $pattern . ')\z~Di', $words, 'identifier', 'gram.y:' . $rule);
    }
}
