<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Lexeme;

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
 * Composes the reviewed SQLite tokenizer cases without post-serialization whitespace changes.
 */
final class DefinitionFactory
{
    /**
     * @param array<string, list<string>> $keywords
     */
    public function create(string $version, array $keywords): ReverseLexemeGenerator
    {
        return new ReverseLexemeGenerator($this->lexemes($version, $keywords), new CandidateResolver(new CombinedSpacingRule()), $version, 'SQLite');
    }

    /**
     * @param array<string, list<string>> $keywords
     */
    public function lexemes(string $version, array $keywords): LexemeGenerator
    {
        return new ChoiceLexemeGenerator(
            (new KeywordDefinitions())->create($version, $keywords),
            new VersionedLexemeGenerator($version, new VersionCase(['sqlite-3.47.2'], new ChoiceLexemeGenerator(
                (new ValueDefinitions())->create(),
                $this->symbols(),
                $this->strictTypes(),
                new JoinLexemeGenerator($keywords['JOIN_KW'] ?? []),
            ), 'sqlite-3.47.2-scanner')),
        );
    }

    /**
     * Declares multi-character scanner operators as single lexemes.
     */
    public function symbols(): LexemeGenerator
    {
        $fixed = ['LP' => '(', 'RP' => ')', 'SEMI' => ';', 'COMMA' => ',', 'DOT' => '.',
            'EQ' => '=', 'LT' => '<', 'LE' => '<=', 'GT' => '>', 'GE' => '>=', 'NE' => '<>',
            'PLUS' => '+', 'MINUS' => '-', 'STAR' => '*', 'SLASH' => '/', 'REM' => '%',
            'BITAND' => '&', 'BITOR' => '|', 'BITNOT' => '~', 'LSHIFT' => '<<', 'RSHIFT' => '>>',
            'CONCAT' => '||', 'PTR' => '->', 'STRICT_TABLE_OPTION' => 'STRICT', 'ROWID_TABLE_OPTION' => 'ROWID'];
        $generators = [];
        foreach ($fixed as $terminal => $text) {
            $generators[] = new MatchingLexemeGenerator($terminal, new FixedLexemeGenerator($text, 'symbol', 'src/tokenize.c:' . $terminal));
        }
        return new ChoiceLexemeGenerator(...$generators);
    }
    /**
     * Declares the complete STRICT type domain from global.c/sqlite3StdType.
     */
    public function strictTypes(): LexemeGenerator
    {
        $types = [];
        foreach (['ANY', 'BLOB', 'INT', 'INTEGER', 'REAL', 'TEXT'] as $name) {
            $types[] = new FixedLexemeGenerator($name, 'type-name', 'src/global.c:sqlite3StdType');
        }
        return new MatchingLexemeGenerator('STRICT_COLUMN_TYPE', new ChoiceLexemeGenerator(...$types));
    }
}
