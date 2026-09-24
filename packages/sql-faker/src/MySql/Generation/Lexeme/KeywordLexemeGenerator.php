<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use Override;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\LexemeSequence;
use SqlFaker\MySql\Generation\Tokenization\MySqlQuoting;

/**
 * Implements lex.h registration classes and sql_lex.cc find_keyword/function lookahead.
 * A SYM_FN spelling needs an opening parenthesis when used as a function.
 * MYSQLlex reads the token after WITH ahead and joins WITH ROLLUP (and, before 8.0, WITH CUBE)
 * into one token, so an identifier spelled like such a keyword right after WITH is backtick-quoted
 * to stay the identifier the grammar derived.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/lex.h
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_lex.cc
 */
final class KeywordLexemeGenerator implements LexemeGenerator
{
    /**
     * @param array<string, list<string>> $symbols SYM registrations for the exact release
     * @param array<string, list<string>> $functions SYM_FN registrations for the exact release
     * @param list<string> $joinedAfterWith Keyword terminals MYSQLlex joins with a preceding WITH
     */
    public function __construct(
        private readonly array $symbols,
        private readonly array $functions,
        private readonly array $joinedAfterWith = [],
    ) {
    }

    /**
     * Supplies registered keyword spellings with their context-dependent lexical use.
     */
    #[Override]
    public function generate(LexemeInput $input): ?LexemeCandidates
    {
        $terminal = $input->terminal()->name;
        $symbols = $this->symbols[$terminal] ?? [];
        $functions = $this->functions[$terminal] ?? [];
        if ($symbols === [] && $functions === []) {
            return null;
        }
        $next = $input->right->parts[0]->lexeme->text ?? null;
        $identifier = $input->terminal()->within('ident')
            || $input->terminal()->within('ident_keyword')
            || $input->terminal()->within('ident_keywords_unambiguous');
        $candidates = [];
        if ($identifier && $input->terminals->nameAt($input->index - 1) === 'WITH' && in_array($terminal, $this->joinedAfterWith, true)) {
            foreach ($symbols as $spelling) {
                $candidates[] = new LexemeSequence([
                    new Lexeme(MySqlQuoting::identifier($spelling), 'identifier', $input->terminal(), 'sql/sql_lex.cc:MYSQLlex:WITH'),
                ], 'mysql.quoted-keyword:' . $spelling);
            }
            return LexemeCandidates::of(...$candidates);
        }
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
