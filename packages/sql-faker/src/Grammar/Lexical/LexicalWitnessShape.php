<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Lexical;

use SqlFaker\Grammar\LexicalCatalogException;
use SqlFaker\Grammar\Terminal;

/**
 * Reads one terminal witness out of the checked-in catalog.
 *
 * A witness is a lexing example: the SQL to feed the server's own lexer, the
 * tokens it must produce, and the coverage units the example accounts for. The
 * generator writes them in two spellings — a compact list and a keyed map — so
 * the compact one is expanded here and everything downstream sees the keyed
 * form only.
 *
 * @phpstan-type Witness array{id: string, sql: string, tokens: list<string>, units: list<string>, context_sql?: string}
 */
final class LexicalWitnessShape
{
    /**
     * Reads one witness, expanding the compact spelling on the way.
     *
     * @param string $terminal Terminal the witness was listed under
     * @param mixed $witness Witness as written in the catalog
     *
     * @return Witness The witness in its keyed form
     *
     * @throws LexicalCatalogException When the entry does not describe a witness
     */
    public function of(string $terminal, mixed $witness): array
    {
        $witness = $this->expanded($witness);

        if (!$this->describesAWitness($witness)) {
            throw LexicalCatalogException::malformedWitness($terminal);
        }

        /** @var Witness $witness */
        return $witness;
    }

    /**
     * Turns the compact list spelling into the keyed one.
     *
     * @param mixed $witness Witness as written in the catalog
     *
     * @return mixed The keyed form, or the input unchanged when it was not compact
     */
    public function expanded(mixed $witness): mixed
    {
        if (!is_array($witness) || !array_is_list($witness) || !in_array(count($witness), [4, 5], true)) {
            return $witness;
        }

        return [
            'id' => $witness[0],
            'sql' => $witness[1],
            'tokens' => $witness[2],
            'units' => $witness[3],
            ...isset($witness[4]) ? ['context_sql' => $witness[4]] : [],
        ];
    }

    /**
     * Reports whether a keyed entry carries everything a witness needs.
     *
     * @param mixed $witness Entry to inspect
     *
     * @return bool True when the entry is a well-formed witness
     */
    public function describesAWitness(mixed $witness): bool
    {
        return is_array($witness)
            && isset($witness['id'], $witness['sql'], $witness['tokens'], $witness['units'])
            && is_string($witness['id'])
            && is_string($witness['sql'])
            && $this->isListOfStrings($witness['tokens'])
            && $this->isListOfStrings($witness['units'])
            && (!isset($witness['context_sql']) || is_string($witness['context_sql']));
    }

    /**
     * Reports whether a value is a plain list holding only strings.
     *
     * @param mixed $value Value to inspect
     *
     * @return bool True for a list of strings, including the empty list
     */
    public function isListOfStrings(mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (!is_string($item)) {
                return false;
            }
        }

        return true;
    }
}
