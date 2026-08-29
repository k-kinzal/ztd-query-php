<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use RuntimeException;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\TerminalInventory;

/**
 * Builds the evidence a MySQL lexical catalogue is made of.
 *
 * A witness is one piece of evidence: text that MySQL's own lexer was observed
 * to read, the tokens it produced, and the lexer states it passed through. They
 * come from three places, and the three do not mix. The profile's own symbol
 * and function tables name most of them. A hand-written sample table names the
 * ones a table cannot express -- a lexer state only reached by a shape, not by
 * a word. And some terminals are reached by punctuation or by entering the
 * parser at all, so they are witnessed by construction rather than by example.
 */
final class MySqlCatalogWitnesses
{
    /**
     * Answers one witness.
     *
     * @param string $id Name this evidence is referred to by
     * @param string $sql Text the lexer was given
     * @param list<string> $tokens Tokens it produced
     * @param list<string> $units Lexer states or parser entries it passed through
     * @param string|null $contextSql Surrounding text the lexeme needs, when it needs any
     *
     * @return array{id: string, sql: string, tokens: list<string>, units: list<string>, context_sql?: string} The witness
     */
    public function witness(string $id, string $sql, array $tokens, array $units, ?string $contextSql = null): array
    {
        $witness = ['id' => $id, 'sql' => $sql, 'tokens' => $tokens, 'units' => $units];
        if ($contextSql !== null) {
            $witness['context_sql'] = $contextSql;
        }

        return $witness;
    }

    /**
     * Answers the witnesses the profile's own tables name.
     *
     * @param array<string, list<string>> $symbols Spellings by symbol terminal
     * @param array<string, list<string>> $functions Spellings by function terminal
     *
     * @return array<string, list<array{id: string, sql: string, tokens: list<string>, units: list<string>, context_sql?: string}>> Witnesses by terminal
     */
    public function fromTables(array $symbols, array $functions): array
    {
        $terminals = [];
        foreach ($symbols as $terminal => $lexemes) {
            if (in_array($terminal, ['NOT2_SYM', 'OR_OR_SYM'], true)) {
                continue;
            }
            foreach ($lexemes as $index => $lexeme) {
                $terminals[$terminal][] = $this->witness(
                    "mysql.symbol.{$terminal}.{$index}",
                    $lexeme,
                    [$terminal],
                    ['MY_LEX_START', 'MY_LEX_IDENT'],
                );
            }
        }
        foreach ($functions as $terminal => $lexemes) {
            foreach ($lexemes as $index => $lexeme) {
                $terminals[$terminal][] = $this->witness(
                    "mysql.function.{$terminal}.{$index}",
                    $lexeme,
                    [$terminal],
                    ['MY_LEX_START', 'MY_LEX_IDENT'],
                    $lexeme . '(',
                );
            }
        }

        return $terminals;
    }

    /**
     * Answers the sample table, with the samples this version's states call for.
     *
     * @param list<string> $states Every state the lexer declares
     * @param bool $dollarQuotedStrings Whether this version reads a dollar-quoted string
     *
     * @param array<string, list<array{id: string, sql: string, tokens: list<string>, units: list<string>, context_sql?: string}>> $terminals Witnesses gathered so far
     *
     * @return array<string, list<array{id: string, sql: string, tokens: list<string>, units: list<string>, context_sql?: string}>> Those witnesses, with the sampled ones added
     *
     * @throws RuntimeException When the version reads dollar-quoted strings but declares no state for them
     */
    public function fromSamples(array $terminals, array $states, bool $dollarQuotedStrings): array
    {
        $samples = $this->stateSamples($states, $dollarQuotedStrings);
        foreach ($this->shapeSamples() as $sample) {
            $samples['@COVERAGE'][] = $sample;
        }

        foreach ($samples as $terminal => $witnesses) {
            foreach ($witnesses as $index => $sample) {
                [$sql, $tokens, $units] = $sample;
                $terminals[$terminal][] = $this->witness(
                    "mysql.family.{$terminal}.{$index}",
                    $sql,
                    $tokens,
                    $units,
                    $sample[3] ?? null,
                );
            }
        }


        return $terminals;
    }

    /**
     * Answers the witnesses that exist because a terminal is punctuation or a parser entry.
     *
     * @param array<string, list<array{id: string, sql: string, tokens: list<string>, units: list<string>, context_sql?: string}>> $terminals Witnesses gathered so far
     * @param Grammar $grammar Grammar whose entry points are being catalogued
     * @param string $version Version tag being catalogued
     *
     * @return array{array<string, list<array{id: string, sql: string, tokens: list<string>, units: list<string>, context_sql?: string}>>, list<string>} The witnesses, and the parser entry tokens they cover
     */
    public function forStructure(array $terminals, Grammar $grammar, string $version): array
    {
        $punctuation = str_split('!%&()*+,-./:;<=>?@[]^{}|~');
        foreach ($punctuation as $terminal) {
            if (!isset($terminals[$terminal])) {
                $terminals[$terminal][] = $this->witness(
                    'mysql.punctuation.' . bin2hex($terminal),
                    $terminal,
                    [$terminal],
                    ['MY_LEX_START', 'MY_LEX_CHAR'],
                );
            }
        }

        $versionedParserTokens = ['END_OF_INPUT'];
        foreach (TerminalInventory::fromGrammar($grammar) as $terminal) {
            if (str_starts_with($terminal, 'GRAMMAR_SELECTOR_')) {
                $versionedParserTokens[] = $terminal;
            }
        }
        foreach ($versionedParserTokens as $terminal) {
            $unit = 'parser-entry:' . $terminal;
            $terminals[$terminal][] = $this->witness(
                'mysql.parser.' . $terminal,
                '',
                [],
                [$unit],
            );
        }
        if (version_compare(substr($version, strlen('mysql-')), '8.0.0', '<')) {
            $terminals['WITH_CUBE_SYM'][] = $this->witness(
                'mysql.parser.WITH_CUBE_SYM',
                'WITH CUBE',
                ['WITH_CUBE_SYM'],
                ['MY_LEX_START', 'MY_LEX_IDENT'],
            );
        }

        return [$terminals, $versionedParserTokens];
    }

    /**
     * Answers the samples that stand for a lexer state a word cannot reach.
     *
     * A symbol table names a state by naming a word that enters it. These
     * states are entered by the shape of the text instead -- a dot between two
     * names, a comparison spelled with two characters, the end of a comment --
     * so they are written out rather than derived.
     *
     * @return list<array{0: string, 1: list<string>, 2: list<string>}> The samples
     */
    public function shapeSamples(): array
    {
        return [
            ['name.column', ['IDENT', '.', 'IDENT'], ['MY_LEX_IDENT_SEP', 'MY_LEX_IDENT_START']],
            ['.5', ['DECIMAL_NUM'], ['MY_LEX_REAL_OR_POINT', 'MY_LEX_REAL']],
            ['<=', ['LE'], ['MY_LEX_CMP_OP']],
            ['<=>', ['EQUAL_SYM'], ['MY_LEX_CMP_OP', 'MY_LEX_LONG_CMP_OP']],
            ['*/', ['*', '/'], ['MY_LEX_END_LONG_COMMENT']],
            [';', [';'], ['MY_LEX_SEMICOLON']],
            ['@name', ['@', 'LEX_HOSTNAME'], ['MY_LEX_USER_END', 'MY_LEX_HOSTNAME']],
            ["@'name'", ['@', 'IDENT_QUOTED'], ['MY_LEX_USER_END', 'MY_LEX_USER_VARIABLE_DELIMITER']],
            ['@@name', ['@', '@', 'IDENT'], ['MY_LEX_USER_END', 'MY_LEX_SYSTEM_VAR', 'MY_LEX_IDENT_OR_KEYWORD']],
        ];
    }

    /**
     * Answers the sample table, with the samples this version's states call for.
     *
     * Which samples belong depends on the version: one reads a dollar sign as
     * the start of a quoted string and another as the start of a name, and a
     * state only some versions declare can only be sampled where it exists.
     *
     * @param list<string> $states Every state the lexer declares
     * @param bool $dollarQuotedStrings Whether this version reads a dollar-quoted string
     *
     * @return array<string, list<array{0: string, 1: list<string>, 2: list<string>, 3?: string}>> Samples by terminal
     *
     * @throws RuntimeException When the version reads dollar-quoted strings but declares no state for them
     */
    public function stateSamples(array $states, bool $dollarQuotedStrings): array
    {
        $samples = (new MySqlLexicalSamples())->all();
        $dollarState = current(array_values(array_filter(
            $states,
            static fn (string $state): bool => str_starts_with($state, 'MY_LEX_IDENT_OR_DOLLAR'),
        )));
        if ($dollarQuotedStrings) {
            if (!is_string($dollarState)) {
                throw new RuntimeException('MySQL dollar-quoted string state was not found.');
            }
            $samples['DOLLAR_QUOTED_STRING_SYM'] = [
                ['$$text$$', ['DOLLAR_QUOTED_STRING_SYM'], ['MY_LEX_START', $dollarState]],
            ];
        } elseif (is_string($dollarState)) {
            $samples['@COVERAGE'][] = ['$identifier', ['IDENT'], ['MY_LEX_START', $dollarState]];
        }
        if (in_array('MY_LEX_ESCAPE', $states, true)) {
            $samples['@COVERAGE'][] = ['\\N', ['NULL_SYM'], ['MY_LEX_START', 'MY_LEX_ESCAPE']];
        }
        if (in_array('MY_LEX_STRING_OR_DELIMITER', $states, true)) {
            $samples['@COVERAGE'][] = ['"text"', ['TEXT_STRING'], ['MY_LEX_START', 'MY_LEX_STRING_OR_DELIMITER']];
        }

        return $samples;
    }
}
