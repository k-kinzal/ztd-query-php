<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use RuntimeException;

/**
 * Accounts for the lexer states a catalogue proves are reachable.
 *
 * A catalogue is only worth trusting if every state MySQL's lexer can enter has
 * some witness that reaches it. Most states are reached incidentally by a
 * witness that was written for a terminal; a few are reached by nothing, and
 * are given a witness of their own. Whichever way a state is covered, a state
 * left uncovered means the model of the lexer is incomplete, and that is worth
 * stopping for rather than shipping a catalogue that quietly omits it.
 */
final class MySqlLexicalCoverage
{
    /**
     * Answers the witnesses that exist only to reach a state nothing else does.
     *
     * @param list<string> $states Every state the lexer declares
     *
     * @return list<array{id: string, sql: string, tokens: list<string>, units: list<string>}> The witnesses to add
     */
    public function fillers(array $states): array
    {
        $fillers = [];
        foreach (['MY_LEX_END', 'MY_LEX_EOL'] as $endState) {
            if (in_array($endState, $states, true)) {
                $fillers[] = ['id' => 'mysql.coverage.' . $endState, 'sql' => '', 'tokens' => [], 'units' => [$endState]];
            }
        }
        if (in_array('MY_LEX_OPERATOR_OR_IDENT', $states, true)) {
            $fillers[] = [
                'id' => 'mysql.coverage.MY_LEX_OPERATOR_OR_IDENT',
                'sql' => 'a + b',
                'tokens' => ['IDENT', '+', 'IDENT'],
                'units' => ['MY_LEX_OPERATOR_OR_IDENT'],
            ];
        }

        return $fillers;
    }

    /**
     * Answers which witness first reaches each unit, refusing one nothing reaches.
     *
     * @param array<string, list<array{id: string, units: list<string>}>> $terminals Every witness, by terminal
     * @param list<string> $units Every unit that has to be reached
     *
     * @return array<string, string> Unit => the id of the witness that first reaches it
     *
     * @throws RuntimeException When a unit is reached by no witness at all
     */
    public function witnessed(array $terminals, array $units): array
    {
        $witnessed = [];
        foreach ($terminals as $witnesses) {
            foreach ($witnesses as $witness) {
                foreach ($witness['units'] as $unit) {
                    if (in_array($unit, $units, true)) {
                        $witnessed[$unit] ??= $witness['id'];
                    }
                }
            }
        }

        $missing = array_values(array_diff($units, array_keys($witnessed)));
        if ($missing !== []) {
            throw new RuntimeException('MySQL source model misses lexical states: ' . implode(', ', $missing));
        }

        return $witnessed;
    }
}
