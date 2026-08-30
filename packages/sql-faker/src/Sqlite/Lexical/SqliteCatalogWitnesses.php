<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Lexical;

/**
 * Writes the witnesses an SQLite lexical catalogue is checked against.
 *
 * A witness is a scrap of SQL together with the tokens SQLite's own tokenizer
 * answers for it and the character classes reaching it goes through, so it is
 * both an example of a terminal and a proof that the model of the tokenizer
 * reaches that class. They come from the keyword list, the lexeme families,
 * and samples written for classes nothing else reaches.
 */
final class SqliteCatalogWitnesses
{
    /**
     * Answers every witness the catalogue holds, by the terminal it stands for.
     *
     * @param array<string, list<string>> $keywords Lexemes each keyword terminal is written as
     * @param array<string, array{0: string, 1: list<string>, 2: list<string>}> $coverageSamples Samples written for classes nothing else reaches
     *
     * @return array<string, list<array{id: string, sql: string, tokens: list<string>, units: list<string>}>> Every witness, by terminal
     */
    public function forProfile(array $keywords, array $coverageSamples): array
    {
        $terminals = [];
        foreach ([$this->fromKeywords($keywords), $this->fromSamples(), $this->fromCoverageSamples($coverageSamples)] as $group) {
            foreach ($group as $terminal => $witnesses) {
                foreach ($witnesses as $witness) {
                    $terminals[$terminal][] = $witness;
                }
            }
        }

        return $terminals;
    }

    /**
     * Answers a witness for every lexeme a keyword terminal is written as.
     *
     * SQLite names a keyword's token after the keyword itself, except WITHIN,
     * which the tokenizer answers as a plain identifier.
     *
     * @param array<string, list<string>> $keywords Lexemes each keyword terminal is written as
     *
     * @return array<string, list<array{id: string, sql: string, tokens: list<string>, units: list<string>}>> The keyword witnesses, by terminal
     */
    public function fromKeywords(array $keywords): array
    {
        $terminals = [];
        foreach ($keywords as $terminal => $lexemes) {
            foreach ($lexemes as $index => $lexeme) {
                $terminals[$terminal][] = $this->witness(
                    "sqlite.keyword.{$terminal}.{$index}",
                    $lexeme,
                    [$terminal === 'WITHIN' ? 'TK_ID' : 'TK_' . $terminal],
                    ['CC_KYWD0'],
                );
            }
        }

        return $terminals;
    }

    /**
     * Answers a witness for every lexeme family the tokenizer recognises.
     *
     * @return array<string, list<array{id: string, sql: string, tokens: list<string>, units: list<string>}>> The family witnesses, by terminal
     */
    public function fromSamples(): array
    {
        $terminals = [];
        foreach ((new SqliteLexicalSamples())->all() as $terminal => $witnesses) {
            foreach ($witnesses as $index => [$sql, $tokens, $units]) {
                $terminals[$terminal][] = $this->witness("sqlite.family.{$terminal}.{$index}", $sql, $tokens, $units);
            }
        }

        return $terminals;
    }

    /**
     * Answers the witnesses written for character classes nothing else reaches.
     *
     * @param array<string, array{0: string, 1: list<string>, 2: list<string>}> $coverageSamples Samples written for those classes
     *
     * @return array<string, list<array{id: string, sql: string, tokens: list<string>, units: list<string>}>> The coverage witnesses, under one terminal
     */
    public function fromCoverageSamples(array $coverageSamples): array
    {
        $terminals = [];
        foreach ($coverageSamples as $id => [$sql, $tokens, $units]) {
            $terminals['@COVERAGE'][] = $this->witness($id, $sql, $tokens, $units);
        }

        return $terminals;
    }

    /**
     * Writes one witness.
     *
     * @param string $id Name the catalogue knows this witness by
     * @param string $sql The scrap of SQL the witness is
     * @param list<string> $tokens Tokens SQLite's tokenizer answers for it
     * @param list<string> $units Character classes reaching it goes through
     *
     * @return array{id: string, sql: string, tokens: list<string>, units: list<string>} The witness
     */
    public function witness(string $id, string $sql, array $tokens, array $units): array
    {
        return [
            'id' => $id,
            'sql' => $sql,
            'tokens' => $tokens,
            'units' => $units,
        ];
    }
}
