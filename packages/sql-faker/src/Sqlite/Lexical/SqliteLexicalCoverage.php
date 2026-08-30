<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Lexical;

use RuntimeException;

/**
 * Accounts for the character classes a catalogue proves are reachable.
 *
 * SQLite's tokenizer decides what to do from the class of the character it is
 * looking at, so the classes the upstream source declares are exactly what a
 * model of it has to reach. A class nothing reaches means the model is
 * incomplete; a class the samples name but the source does not declare means
 * the model has drifted from the release it claims to describe. Both are worth
 * stopping for rather than shipping a catalogue that quietly disagrees.
 */
final class SqliteLexicalCoverage
{
    /**
     * Answers which sample first reaches each character class.
     *
     * @param array<string, array{0: string, 1: list<string>, 2: list<string>}> $coverageSamples Samples written for classes nothing else reaches
     *
     * @return array<string, string> Class => the id of the sample that first reaches it
     */
    public function witnessed(array $coverageSamples): array
    {
        $witnessed = [];
        foreach ($coverageSamples as $id => [, , $units]) {
            foreach ($units as $unit) {
                $witnessed[$unit] ??= $id;
            }
        }

        return $witnessed;
    }

    /**
     * Refuses a catalogue that disagrees with the classes the source declares.
     *
     * @param list<string> $classes Character classes the upstream source declares
     * @param array<string, string> $witnessed Class => the sample that reaches it
     * @param array<string, string> $excluded Class => the reason nothing reaches it
     *
     * @throws RuntimeException When a declared class is unaccounted for, or an accounted class is not declared
     */
    public function assertClassified(array $classes, array $witnessed, array $excluded): void
    {
        $classified = [...array_keys($witnessed), ...array_keys($excluded)];

        $missing = array_values(array_diff($classes, $classified));
        if ($missing !== []) {
            throw new RuntimeException('SQLite source model misses character classes: ' . implode(', ', $missing));
        }

        $unknown = array_values(array_diff($classified, $classes));
        if ($unknown !== []) {
            throw new RuntimeException('SQLite source model references unknown character classes: ' . implode(', ', $unknown));
        }
    }
}
