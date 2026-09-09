<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Lexeme;

use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\SequenceLexemeGenerator;
use SqlFaker\Grammar\Generation\Version\VersionCase;
use SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator;

/**
 * sql/sql_lex.cc single-character returns, scanner operators, EOF and parser lookahead phrases.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/lex.h
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_lex.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 */
final class SymbolDefinitions
{
    /**
     * Declares scanner output not supplied by lex.h keyword registrations.
     */
    public function create(string $version): LexemeGenerator
    {
        $symbols = [];
        foreach (str_split('()[],.;:+-*/%=<>!|&^~@{}') as $character) {
            $symbols[] = new MatchingLexemeGenerator($character, new FixedLexemeGenerator($character, 'symbol', 'sql/sql_lex.cc:MY_LEX_CHAR'));
        }
        foreach (['PARAM_MARKER' => '?', 'SET_VAR' => ':=', 'OR2_SYM' => '||', 'NOT2_SYM' => 'NOT'] as $terminal => $text) {
            $symbols[] = new MatchingLexemeGenerator($terminal, new FixedLexemeGenerator($text, 'symbol', 'sql/sql_lex.cc:' . $terminal));
        }
        $symbols[] = new MatchingLexemeGenerator('CONCAT_FUNCTION_NAME', new FixedLexemeGenerator('CONCAT', 'function', 'sql/sql_yacc.yy:simple_expr:Item_func_concat'));
        return new ChoiceLexemeGenerator(...[...$symbols, $this->json($version), $this->phrases($version), $this->selectors($version)]);
    }

    /**
     * Declares JSON operators only for the checked-in releases that implement them.
     */
    public function json(string $version): LexemeGenerator
    {
        return new VersionedLexemeGenerator($version, new VersionCase(
            ['mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'],
            new ChoiceLexemeGenerator(
                new MatchingLexemeGenerator('JSON_SEPARATOR_SYM', new FixedLexemeGenerator('->', 'operator', 'sql/sql_lex.cc:MY_LEX_CHAR:json')),
                new MatchingLexemeGenerator('JSON_UNQUOTED_SEPARATOR_SYM', new FixedLexemeGenerator('->>', 'operator', 'sql/sql_lex.cc:MY_LEX_CHAR:json-unquoted')),
            ),
            'mysql-json-operators',
        ));
    }

    /**
     * Declares lookahead phrases as sequences; CUBE is present only in the older lexer releases.
     */
    public function phrases(string $version): LexemeGenerator
    {
        return new ChoiceLexemeGenerator(
            new MatchingLexemeGenerator('WITH_ROLLUP_SYM', $this->phrase('ROLLUP')),
            new MatchingLexemeGenerator('WITH_CUBE_SYM', new VersionedLexemeGenerator($version, new VersionCase(
                ['mysql-5.6.51', 'mysql-5.7.44'],
                $this->phrase('CUBE'),
                'mysql-with-cube',
            ))),
        );
    }

    /**
     * Both words belong to one original lookahead token and share its phrase boundary.
     */
    public function phrase(string $following): LexemeGenerator
    {
        return new SequenceLexemeGenerator(
            new FixedLexemeGenerator('WITH', 'keyword', 'sql/sql_lex.cc:MYSQLlex', 'mysql-with-phrase'),
            new FixedLexemeGenerator($following, 'keyword', 'sql/sql_lex.cc:MYSQLlex', 'mysql-with-phrase'),
        );
    }

    /**
     * Declares the non-output tokens injected by the MySQL 8+ parser entry-point selector.
     */
    public function selectors(string $version): LexemeGenerator
    {
        $selectors = [];
        foreach (array_slice($this->nonOutput(), 1) as $terminal) {
            $selectors[] = new MatchingLexemeGenerator($terminal, new FixedLexemeGenerator('', 'marker', 'sql/sql_lex.cc:grammar_selector_token'));
        }
        return new ChoiceLexemeGenerator(
            new MatchingLexemeGenerator($this->nonOutput()[0], new FixedLexemeGenerator('', 'marker', 'sql/sql_lex.cc:MY_LEX_EOL')),
            new VersionedLexemeGenerator($version, new VersionCase(
                ['mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'],
                new ChoiceLexemeGenerator(...$selectors),
                'mysql-parser-selectors',
            )),
        );
    }

    /**
     * The marker declaration also supplies emptiness metadata to token-generation budgets.
     * @return non-empty-list<string>
     */
    public function nonOutput(): array
    {
        return ['END_OF_INPUT', 'GRAMMAR_SELECTOR_CTE', 'GRAMMAR_SELECTOR_DERIVED_EXPR', 'GRAMMAR_SELECTOR_EXPR', 'GRAMMAR_SELECTOR_GCOL', 'GRAMMAR_SELECTOR_PART'];
    }
}
