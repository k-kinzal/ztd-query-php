<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Lexical;

use RuntimeException;

/**
 * Accounts for the scanner rules a catalogue proves are reachable.
 *
 * PostgreSQL's scanner is a numbered list of rules, and a catalogue is only
 * worth trusting if every rule that can produce a token has some witness that
 * reaches it. A few rules can only report a lexical error, so no successful
 * witness will ever reach them; those are named here with the reason, and
 * everything else must be witnessed or the model of the scanner is incomplete.
 */
final class PgLexicalCoverage
{
    /**
     * The rules that can only report a lexical error, and why.
     */
    private const ERROR_ONLY_RULES = [
        'rule:25' => 'Malformed Unicode surrogate continuation is an error-only scanner branch.',
        'rule:26' => 'Missing Unicode surrogate continuation is an error-only scanner branch.',
        'rule:27' => 'Malformed Unicode escape is an error-only scanner branch.',
        'rule:31' => 'A backslash immediately before EOF belongs to an unterminated string error path.',
        'rule:56' => 'Trailing junk after a positional parameter is an error-only scanner branch.',
        'rule:61' => 'Invalid hexadecimal integer prefix is an error-only scanner branch.',
        'rule:62' => 'Invalid octal integer prefix is an error-only scanner branch.',
        'rule:63' => 'Invalid binary integer prefix is an error-only scanner branch.',
        'rule:67' => 'Incomplete exponent is an error-only scanner branch.',
        'rule:68' => 'Trailing identifier junk after an integer is an error-only scanner branch.',
        'rule:69' => 'Trailing identifier junk after a numeric is an error-only scanner branch.',
        'rule:70' => 'Trailing identifier junk after a real is an error-only scanner branch.',
    ];

    /**
     * Answers every unit a catalogue has to reach.
     *
     * @param int $ruleCount How many rules the scanner declares, counting the jam rule
     * @param list<string> $parserModes Alternative modes the parser can be entered in
     *
     * @return list<string> Every unit that has to be reached
     */
    public function units(int $ruleCount, array $parserModes): array
    {
        $units = [];
        for ($rule = 1; $rule <= $ruleCount; $rule++) {
            $units[] = 'rule:' . $rule;
        }
        foreach ($parserModes as $mode) {
            $units[] = 'parser-mode:' . $mode;
        }

        return $units;
    }

    /**
     * Answers which witness first reaches each unit.
     *
     * @param array<string, list<array{id: string, units: list<string>}>> $terminals Every witness, by terminal
     *
     * @return array<string, string> Unit => the id of the witness that first reaches it
     */
    public function witnessed(array $terminals): array
    {
        $witnessed = [];
        foreach ($terminals as $witnesses) {
            foreach ($witnesses as $witness) {
                foreach ($witness['units'] as $unit) {
                    $witnessed[$unit] ??= $witness['id'];
                }
            }
        }

        return $witnessed;
    }

    /**
     * Answers the units no successful witness will ever reach, and why.
     *
     * The last rule is Flex's own jam rule, which stands for input the scanner
     * has no rule for at all rather than for a branch of PostgreSQL's language.
     *
     * @param int $ruleCount How many rules the scanner declares, counting the jam rule
     *
     * @return array<string, string> Unit => the reason nothing reaches it
     */
    public function excluded(int $ruleCount): array
    {
        return self::ERROR_ONLY_RULES + [
            'rule:' . $ruleCount => 'Flex default jam rule is not a PostgreSQL lexical language branch.',
        ];
    }

    /**
     * Refuses a catalogue that leaves a unit neither witnessed nor accounted for.
     *
     * @param list<string> $units Every unit that has to be reached
     * @param array<string, string> $witnessed Unit => the witness that reaches it
     * @param array<string, string> $excluded Unit => the reason nothing reaches it
     *
     * @throws RuntimeException When a unit is neither witnessed nor excluded
     */
    public function assertCovered(array $units, array $witnessed, array $excluded): void
    {
        $missing = array_values(array_diff($units, array_keys($witnessed), array_keys($excluded)));
        if ($missing !== []) {
            throw new RuntimeException('PostgreSQL source model misses scanner rules: ' . implode(', ', $missing));
        }
    }
}
