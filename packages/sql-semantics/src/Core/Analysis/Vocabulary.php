<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use LogicException;
use SqlSemantics\Statement\Element;

/**
 * The generated construction vocabulary of one grammar release.
 *
 * A recipe describes one alternative of one grammar rule: the value class
 * and the positions of its fields, a finite choice, or a forwarding to the
 * single value it is made of. Every recipe names the symbols of its form, so
 * a form can be found by the symbols it is written with, and a name or
 * literal role can be followed through forwarding rules to the class that
 * holds one terminal.
 *
 * @phpstan-type Built array{constant: string, symbols: list<string>}|array{class: class-string<Element>, fields: list<int>, symbols: list<string>}
 * @phpstan-type Recipe array{forward: int, symbols: list<string>}|Built
 * @visibility SqlSemantics
 */
final class Vocabulary
{
    /**
     * @var array<string, list<string>> The rules each rule forwards to, itself first
     */
    private array $reach = [];

    /**
     * @var array<class-string<Element>, array{rule: string, symbols: list<string>}>|null The form of each value class
     */
    private ?array $shapes = null;

    /**
     * @param array<string, array<int, Recipe>> $recipes The recipes by rule and alternative
     * @param array<string, list<string>> $classes The member terminals of each terminal class
     * @param array<string, string> $fallbacks The terminal the parser retries with, by the terminal it rejects
     */
    public function __construct(
        public readonly array $recipes,
        public readonly array $classes = [],
        public readonly array $fallbacks = [],
    ) {
    }

    /**
     * Loads the vocabulary a database package generated for a release.
     *
     * @throws LogicException When the generated resource is missing or invalid
     */
    public static function fromFile(string $path): self
    {
        if (!is_file($path)) {
            throw new LogicException('Missing statement model resource: ' . $path);
        }
        $vocabulary = require $path;
        if (!$vocabulary instanceof self) {
            throw new LogicException('Invalid statement model resource: ' . $path);
        }

        return $vocabulary;
    }

    /**
     * Answers the recipe of one alternative of a rule.
     *
     * @return Recipe|null
     */
    public function recipe(string $rule, int $ordinal): ?array
    {
        return $this->recipes[$rule][$ordinal] ?? null;
    }

    /**
     * Answers the recipe of the form of a rule that is written with exactly the symbols.
     *
     * @param list<string> $symbols The grammar symbols of the form, terminals by their terminal names
     * @return Recipe|null
     */
    public function form(string $rule, array $symbols): ?array
    {
        foreach ($this->recipes[$rule] ?? [] as $recipe) {
            if ($recipe['symbols'] === $symbols) {
                return $recipe;
            }
        }

        return null;
    }

    /**
     * Answers the rule and symbols of the form a value is an instance of, or null for a choice or a foreign value.
     *
     * @return array{rule: string, symbols: list<string>}|null
     */
    public function shape(Element $value): ?array
    {
        if ($this->shapes === null) {
            $this->shapes = [];
            foreach ($this->recipes as $rule => $alternatives) {
                foreach ($alternatives as $recipe) {
                    if (isset($recipe['class'])) {
                        $this->shapes[$recipe['class']] = ['rule' => $rule, 'symbols' => $recipe['symbols']];
                    }
                }
            }
        }

        return $this->shapes[$value::class] ?? null;
    }

    /**
     * Reports whether a symbol names a rule of the grammar rather than a terminal.
     */
    public function isRule(string $symbol): bool
    {
        return isset($this->recipes[$symbol]);
    }

    /**
     * Lists the rules a role can stand for through forwarding alternatives, the role first.
     *
     * @return list<string>
     */
    public function reaches(string $role): array
    {
        if (isset($this->reach[$role])) {
            return $this->reach[$role];
        }
        $seen = [$role => true];
        $pending = [$role];
        while ($pending !== []) {
            $rule = array_shift($pending);
            foreach ($this->recipes[$rule] ?? [] as $recipe) {
                if (!isset($recipe['forward'])) {
                    continue;
                }
                $target = $recipe['symbols'][$recipe['forward']] ?? null;
                if ($target !== null && !isset($seen[$target])) {
                    $seen[$target] = true;
                    $pending[] = $target;
                }
            }
        }

        return $this->reach[$role] = array_keys($seen);
    }

    /**
     * Answers the recipe of the single-terminal form a role admits for a terminal, following forwarding rules.
     *
     * The terminal may be admitted as itself, as a member of a terminal
     * class, or through the terminal the parser falls back to.
     *
     * @return Built|null
     */
    public function leaf(string $role, string $terminal): ?array
    {
        $names = $this->terminals($terminal);
        foreach ($this->reaches($role) as $rule) {
            foreach ($this->recipes[$rule] ?? [] as $recipe) {
                if (!isset($recipe['forward']) && count($recipe['symbols']) === 1 && in_array($recipe['symbols'][0], $names, true)) {
                    return $recipe;
                }
            }
        }

        return null;
    }

    /**
     * Lists the symbols a lexed terminal can stand for: itself, its classes, and its fallback with that one's classes.
     *
     * @return list<string>
     */
    public function terminals(string $terminal): array
    {
        $names = [$terminal];
        foreach ($this->classes as $class => $members) {
            if (in_array($terminal, $members, true)) {
                $names[] = $class;
            }
        }
        $fallback = $this->fallbacks[$terminal] ?? null;
        if ($fallback !== null && $fallback !== $terminal) {
            array_push($names, ...$this->terminals($fallback));
        }

        return array_values(array_unique($names));
    }

    /**
     * Constructs the value of a recipe from the arguments of its fields.
     *
     * @param Recipe $recipe
     * @param list<Element|string> $arguments One argument per field, in field order
     *
     * @throws LogicException When the recipe forwards or the arguments do not match its fields
     */
    public function build(array $recipe, array $arguments): Element
    {
        if (isset($recipe['constant'])) {
            if ($arguments !== []) {
                throw new LogicException('A choice takes no arguments');
            }
            $choice = constant($recipe['constant']);
            if (!$choice instanceof Element) {
                throw new LogicException('A model choice must implement Element');
            }

            return $choice;
        }
        if (!isset($recipe['class'])) {
            throw new LogicException('A forwarding recipe constructs no value of its own');
        }
        if (count($arguments) !== count($recipe['fields'])) {
            throw new LogicException('Expected ' . count($recipe['fields']) . ' arguments for ' . $recipe['class']);
        }

        return new ($recipe['class'])(...$arguments);
    }
}
