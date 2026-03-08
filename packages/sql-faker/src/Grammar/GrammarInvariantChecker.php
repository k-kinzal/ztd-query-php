<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use InvalidArgumentException;

/**
 * Checks structural invariants of compiled grammar objects.
 */
final class GrammarInvariantChecker
{
    /** @var array<string, object> */
    private array $ruleMap;

    /** @var class-string */
    private string $nonTerminalClass;

    /** @var array<string, true> */
    private array $terminatingRules;

    /**
     * @param class-string $nonTerminalClass
     */
    public function __construct(
        object $grammar,
        string $nonTerminalClass = NonTerminal::class,
    ) {
        $this->readStringProperty($grammar, 'startSymbol');
        $this->ruleMap = $this->readObjectMapProperty($grammar, 'ruleMap');
        $this->nonTerminalClass = $nonTerminalClass;
        $this->terminatingRules = $this->computeTerminatingRules();
    }

    /**
     * @param list<string> $entryRules
     * @return list<string>
     */
    public function missingEntryRules(array $entryRules): array
    {
        $missing = [];
        foreach (array_values(array_unique($entryRules)) as $entryRule) {
            if (!isset($this->ruleMap[$entryRule])) {
                $missing[] = $entryRule;
            }
        }

        sort($missing);

        return $missing;
    }

    /**
     * @return array<string, list<string>>
     */
    public function undefinedReferences(): array
    {
        $undefined = [];

        foreach ($this->ruleMap as $lhs => $rule) {
            foreach ($this->alternatives($rule) as $alternative) {
                foreach ($this->symbols($alternative) as $symbol) {
                    if ($symbol instanceof $this->nonTerminalClass && !isset($this->ruleMap[$this->symbolValue($symbol)])) {
                        $undefined[$lhs][$this->symbolValue($symbol)] = true;
                    }
                }
            }
        }

        ksort($undefined);

        return array_map(
            static function (array $targets): array {
                $values = array_keys($targets);
                sort($values);

                return $values;
            },
            $undefined,
        );
    }

    /**
     * @return list<string>
     */
    public function rulesWithoutAlternatives(): array
    {
        $rules = array_map(
            static fn (string $name): string => $name,
            array_keys(array_filter(
                $this->ruleMap,
                fn (object $rule): bool => $this->alternatives($rule) === [],
            )),
        );

        sort($rules);

        return $rules;
    }

    public function canTerminate(string $ruleName): bool
    {
        return isset($this->terminatingRules[$ruleName]);
    }

    /**
     * @param list<string> $entryRules
     * @return list<string>
     */
    public function reachableRules(array $entryRules): array
    {
        $reachable = [];
        $stack = array_values(array_unique($entryRules));

        while ($stack !== []) {
            $ruleName = array_pop($stack);
            if (isset($reachable[$ruleName]) || !isset($this->ruleMap[$ruleName])) {
                continue;
            }

            $reachable[$ruleName] = true;

            foreach ($this->alternatives($this->ruleMap[$ruleName]) as $alternative) {
                foreach ($this->symbols($alternative) as $symbol) {
                    if ($symbol instanceof $this->nonTerminalClass && !isset($reachable[$this->symbolValue($symbol)])) {
                        $stack[] = $this->symbolValue($symbol);
                    }
                }
            }
        }

        $rules = array_keys($reachable);
        sort($rules);

        return $rules;
    }

    /**
     * @param list<string> $entryRules
     * @return list<string>
     */
    public function unreachableRules(array $entryRules): array
    {
        $reachable = array_fill_keys($this->reachableRules($entryRules), true);
        $unreachable = array_values(array_filter(
            array_keys($this->ruleMap),
            static fn (string $ruleName): bool => !isset($reachable[$ruleName]),
        ));

        sort($unreachable);

        return $unreachable;
    }

    /**
     * @param list<string> $entryRules
     * @return list<string>
     */
    public function nonTerminatingReachableRules(array $entryRules): array
    {
        $nonTerminating = array_filter(
            $this->reachableRules($entryRules),
            fn (string $ruleName): bool => !$this->canTerminate($ruleName),
        );

        sort($nonTerminating);

        return $nonTerminating;
    }

    /**
     * @return array<string, true>
     */
    private function computeTerminatingRules(): array
    {
        $terminating = [];

        $changed = true;
        while ($changed) {
            $changed = false;

            foreach ($this->ruleMap as $ruleName => $rule) {
                if (isset($terminating[$ruleName])) {
                    continue;
                }

                foreach ($this->alternatives($rule) as $alternative) {
                    if ($this->productionTerminates($alternative, $terminating)) {
                        $terminating[$ruleName] = true;
                        $changed = true;
                        break;
                    }
                }
            }
        }

        return $terminating;
    }

    /**
     * @param array<string, true> $terminating
     */
    private function productionTerminates(object $production, array $terminating): bool
    {
        foreach ($this->symbols($production) as $symbol) {
            if ($symbol instanceof $this->nonTerminalClass && !isset($terminating[$this->symbolValue($symbol)])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<object>
     */
    private function alternatives(object $rule): array
    {
        /** @var list<object> $alternatives */
        $alternatives = $this->readObjectListProperty($rule, 'alternatives', 'Production rule must expose an alternatives array.');

        return $alternatives;
    }

    /**
     * @return list<object>
     */
    private function symbols(object $production): array
    {
        /** @var list<object> $symbols */
        $symbols = $this->readObjectListProperty($production, 'symbols', 'Production must expose a symbols array.');

        return $symbols;
    }

    private function symbolValue(object $symbol): string
    {
        return $this->readStringProperty($symbol, 'value', 'Grammar symbol must expose a string value property.');
    }

    private function readStringProperty(object $object, string $property, string $message = ''): string
    {
        $properties = get_object_vars($object);
        $value = $properties[$property] ?? null;
        if (!is_string($value)) {
            throw new InvalidArgumentException($message !== '' ? $message : sprintf('Object property %s must be a string.', $property));
        }

        return $value;
    }

    /**
     * @return array<string, object>
     */
    private function readObjectMapProperty(object $object, string $property): array
    {
        $properties = get_object_vars($object);
        $value = $properties[$property] ?? null;
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('Object property %s must be an array.', $property));
        }

        $map = [];
        foreach ($value as $key => $item) {
            if (!is_string($key) || !is_object($item)) {
                throw new InvalidArgumentException(sprintf('Object property %s must be an object map.', $property));
            }

            $map[$key] = $item;
        }

        return $map;
    }

    /**
     * @return list<object>
     */
    private function readObjectListProperty(object $object, string $property, string $message): array
    {
        $properties = get_object_vars($object);
        $value = $properties[$property] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException($message);
        }

        $list = [];
        foreach ($value as $item) {
            if (!is_object($item)) {
                throw new InvalidArgumentException($message);
            }

            $list[] = $item;
        }

        return $list;
    }
}
