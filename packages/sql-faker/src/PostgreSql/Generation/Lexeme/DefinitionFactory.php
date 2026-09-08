<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Lexeme;

use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator;
use SqlFaker\Grammar\Generation\Output\CandidateResolver;
use SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator;
use SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule;
use SqlFaker\Grammar\Generation\Version\VersionCase;
use SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator;

/**
 * Composes REL_17_2 scan.l domains and parser.c selector/lookahead tokens.
 */
final class DefinitionFactory
{
    /**
     * @param array<string, list<string>> $keywords
     */
    public function create(string $version, array $keywords): ReverseLexemeGenerator
    {
        return new ReverseLexemeGenerator($this->lexemes($version, $keywords), new CandidateResolver(new CombinedSpacingRule()), $version, 'PostgreSQL');
    }

    /**
     * @param array<string, list<string>> $keywords
     */
    public function lexemes(string $version, array $keywords): LexemeGenerator
    {
        return new ChoiceLexemeGenerator(
            (new KeywordDefinitions())->create($version, $keywords),
            new VersionedLexemeGenerator($version, new VersionCase(['pg-17.2'], new ChoiceLexemeGenerator(
                (new ValueDefinitions())->create(),
                (new ContextualNameDefinitions())->create(),
                new HashBoundLexemeGenerator(),
                $this->symbols(),
            ), 'pg-17.2-scanner')),
        );
    }

    /**
     * Declares scan.l self tokens and fixed operators, plus parser.c's non-output selector tokens.
     */
    public function symbols(): LexemeGenerator
    {
        $generators = [];
        foreach (str_split('(),;[]:+-*/%^<>=.') as $character) {
            $generators[] = new MatchingLexemeGenerator($character, new FixedLexemeGenerator($character, 'symbol', 'scan.l:self'));
        }
        $fixed = ['TYPECAST' => '::', 'DOT_DOT' => '..', 'COLON_EQUALS' => ':=', 'EQUALS_GREATER' => '=>',
            'NOT_EQUALS' => '<>', 'LESS_EQUALS' => '<=', 'GREATER_EQUALS' => '>='];
        foreach ($fixed as $terminal => $text) {
            $generators[] = new MatchingLexemeGenerator($terminal, new FixedLexemeGenerator($text, 'symbol', 'scan.l/parser.c:' . $terminal));
        }
        foreach ($this->nonOutput() as $terminal) {
            $generators[] = new MatchingLexemeGenerator($terminal, new FixedLexemeGenerator('', 'marker', 'parser.c:base_yylex'));
        }
        return new ChoiceLexemeGenerator(...$generators);
    }

    /**
     * Supplies non-output selector declarations to both lexical realization and token budgets.
     * @return list<string>
     */
    public function nonOutput(): array
    {
        return ['MODE_TYPE_NAME', 'MODE_PLPGSQL_EXPR', 'MODE_PLPGSQL_ASSIGN1', 'MODE_PLPGSQL_ASSIGN2', 'MODE_PLPGSQL_ASSIGN3'];
    }
}
