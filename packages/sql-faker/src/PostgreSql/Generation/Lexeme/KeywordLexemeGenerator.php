<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Lexeme;

use Override;
use SqlFaker\Generation\Candidate\RegisteredLexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\PostgreSql\Lookahead\PgLookahead;

/**
 * Combines kwlist.h registrations with parser.c/base_yylex's required followers for lookahead tokens.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/parser.c
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/include/parser/kwlist.h
 */
final class KeywordLexemeGenerator implements LexemeGenerator
{
    /**
     * Binds only the selected release's keyword registrations.
     * @param array<string, list<string>> $keywords
     */
    public function __construct(private readonly array $keywords)
    {
    }

    /**
     * Rejects an impossible lookahead context before selecting a registered spelling.
     * @throws \SqlFaker\Generation\Exception\LexicalException When a declared keyword registration is missing
     */
    #[Override]
    public function generate(LexemeInput $input): LexemeCandidates
    {
        $aliases = [];
        foreach (PgLookahead::definitions() as $base => $rule) {
            $aliases[$rule['token']] = $base;
            if ($input->terminal()->name !== $rule['token']) {
                continue;
            }
            $follower = $input->right->parts[0]->lexeme->origin->name ?? null;
            if (!in_array($follower, $rule['followed_by'], true)) {
                return LexemeCandidates::of();
            }
        }
        return (new RegisteredLexemeGenerator($this->keywords, 'src/include/parser/kwlist.h', $aliases))->generate($input);
    }
}
