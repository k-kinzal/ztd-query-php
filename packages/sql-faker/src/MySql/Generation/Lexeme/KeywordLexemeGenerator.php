<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use Override;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\LexemeSequence;
use SqlFaker\Grammar\LexicalException;

/**
 * Implements lex.h registration classes and sql_lex.cc find_keyword/function lookahead.
 * A SYM_FN spelling needs an opening parenthesis when used as a function.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/lex.h
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_lex.cc
 */
final class KeywordLexemeGenerator implements LexemeGenerator
{
    /**
     * @param array<string, list<string>> $symbols SYM registrations for the exact release
     * @param array<string, list<string>> $functions SYM_FN registrations for the exact release
     */
    public function __construct(private readonly array $symbols, private readonly array $functions)
    {
    }

    /**
     * Supplies registered keyword spellings with their context-dependent lexical use.
     * @throws LexicalException When a declared handler has no corresponding upstream registration
     */
    #[Override]
    public function generate(LexemeInput $input): LexemeCandidates
    {
        $terminal = $input->terminal()->name;
        $symbols = $this->symbols[$terminal] ?? [];
        $functions = $this->functions[$terminal] ?? [];
        if ($symbols === [] && $functions === []) {
            throw new LexicalException('Missing MySQL registration for declared keyword ' . $terminal);
        }
        $next = $input->right->parts[0]->lexeme->text ?? null;
        $identifier = $input->terminal()->within('ident')
            || $input->terminal()->within('ident_keyword')
            || $input->terminal()->within('ident_keywords_unambiguous');
        $candidates = [];
        foreach ($symbols as $spelling) {
            $candidates[] = new LexemeSequence([
                new Lexeme($spelling, 'keyword', $input->terminal(), 'sql/lex.h:SYM'),
            ], 'mysql.keyword:' . $spelling);
        }
        foreach ($functions as $spelling) {
            if ($next !== '(' && $symbols !== []) {
                continue;
            }
            $kind = $next === '(' && !$identifier ? 'function' : 'identifier';
            $candidates[] = new LexemeSequence([
                new Lexeme($spelling, $kind, $input->terminal(), 'sql/sql_lex.cc:find_keyword'),
            ], 'mysql.function:' . $spelling);
        }
        return LexemeCandidates::of(...$candidates);
    }
}
