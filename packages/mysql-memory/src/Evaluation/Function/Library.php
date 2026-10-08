<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function;

use MySqlMemory\Evaluation\Function\Text\Cases;
use MySqlMemory\Evaluation\Function\Text\Substrings;

/**
 * The built-in functions the emulator evaluates, by name.
 *
 * @visibility MySqlMemory
 */
final class Library
{
    private static ?self $instance = null;

    /**
     * @param array<string, Routine> $routines The functions, by upper-case name
     */
    public function __construct(public readonly array $routines)
    {
    }

    /**
     * Answers the library of every family.
     */
    public static function instance(): self
    {
        if (self::$instance === null) {
            $routines = [];
            foreach ([new Strings(), new Cases(), new Substrings(), new Text\Formats(), new Text\Lists(), new Text\Soundex(), new Text\Encodings(), new Text\Radixes(), new Text\Compression(), new Pattern\Patterns(),new Measures(), new Numbers(), new Math\Numerics(), new Math\Extremes(), new Math\Intervals(), new Control(), new Introspection(), new Server\Locks(), new Server\Pauses(), new Server\Performance(), new Server\Miscellany(),new Dates(), new Time\Calendars(), new Time\Clocks(), new Time\Epochs(), new Time\Formatting(), new Time\Parsing(),new Special\Replication(), new Special\Vectors(), new Special\Markup(), new Special\Digests(),new Json\Constructions(), new Json\Modifications(), new Json\Searches(), new Json\Schemas(),new Json\Outputs(),new Digest\Hashes(), new Digest\Identifiers(), new Digest\Addresses(), new Digest\Ciphers()] as $family) {
                foreach ($family->routines() as $routine) {
                    $routines[$routine->name] = $routine;
                }
            }
            self::$instance = new self($routines);
        }

        return self::$instance;
    }

    /**
     * Finds a function by name, without regard to case, or answers null.
     */
    public function find(string $name): ?Routine
    {
        return $this->routines[strtoupper($name)] ?? null;
    }
}
