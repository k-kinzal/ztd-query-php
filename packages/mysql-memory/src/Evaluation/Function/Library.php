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
            foreach ([new Strings(), new Cases(), new Substrings(), new Measures(), new Numbers(), new Control(), new Introspection(), new Dates()] as $family) {
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
