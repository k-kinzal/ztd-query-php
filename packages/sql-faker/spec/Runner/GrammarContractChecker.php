<?php

declare(strict_types=1);

namespace Spec\Runner;

use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\Production;

/**
 * Answers structural questions about a grammar, such as undefined
 * references, reachability, and whether entry rules can terminate.
 */
final class GrammarContractChecker
{
    /** @var array<string, true> */
    private array $terminatingRules;

    public function __construct(
        private readonly Grammar $grammar,
    ) {
        $this->terminatingRules = $this->computeTerminatingRules();
    }

    /**
     * @return array<string, list<string>>
     */
    public function undefinedReferences(): array
    {
        $undefined = [];
        foreach ($this->grammar->rules as $ruleName => $rule) {
            foreach ($rule->alternatives as $alternative) {
                foreach ($alternative->references() as $reference) {
                    if (!isset($this->grammar->rules[$reference])) {
                        $undefined[$ruleName][$reference] = true;
                    }
                }
            }
        }

        ksort($undefined);

        return array_map(
            static function (array $references): array {
                $values = array_keys($references);
                sort($values);

                return $values;
            },
            $undefined,
        );
    }

    /**
     * @param list<string> $entries
     * @return list<string>
     */
    public function missingEntries(array $entries): array
    {
        $missing = [];
        foreach ($entries as $entry) {
            if (!isset($this->grammar->rules[$entry])) {
                $missing[] = $entry;
            }
        }

        sort($missing);

        return array_values(array_unique($missing));
    }

    /**
     * @return list<string>
     */
    public function rulesWithoutAlternatives(): array
    {
        $empty = [];
        foreach ($this->grammar->rules as $ruleName => $rule) {
            if ($rule->alternatives === []) {
                $empty[] = $ruleName;
            }
        }

        sort($empty);

        return $empty;
    }

    /**
     * Reports whether the named rule can derive a finite terminal sequence.
     */
    public function canTerminate(string $ruleName): bool
    {
        return isset($this->terminatingRules[$ruleName]);
    }

    /**
     * @param list<string> $entries
     * @return list<string>
     */
    public function reachableRules(array $entries): array
    {
        $reachable = [];
        $stack = array_values(array_unique($entries));

        while ($stack !== []) {
            /** @var string $ruleName */
            $ruleName = array_pop($stack);
            if (isset($reachable[$ruleName]) || !isset($this->grammar->rules[$ruleName])) {
                continue;
            }

            $reachable[$ruleName] = true;
            foreach ($this->grammar->rules[$ruleName]->alternatives as $alternative) {
                foreach ($alternative->references() as $reference) {
                    if (!isset($reachable[$reference])) {
                        $stack[] = $reference;
                    }
                }
            }
        }

        $rules = array_keys($reachable);
        sort($rules);

        return $rules;
    }

    /**
     * @param list<string> $entries
     * @return list<string>
     */
    public function nonTerminatingReachableRules(array $entries): array
    {
        $nonTerminating = [];
        foreach ($this->reachableRules($entries) as $ruleName) {
            if (!$this->canTerminate($ruleName)) {
                $nonTerminating[] = $ruleName;
            }
        }

        sort($nonTerminating);

        return $nonTerminating;
    }

    /**
     * @param list<string> $entries
     * @param list<string> $rules
     * @return list<string>
     */
    public function unreachableRules(array $entries, array $rules): array
    {
        $reachable = array_fill_keys($this->reachableRules($entries), true);
        $unreachable = [];

        foreach ($rules as $ruleName) {
            if (!isset($reachable[$ruleName])) {
                $unreachable[] = $ruleName;
            }
        }

        sort($unreachable);

        return $unreachable;
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
            foreach ($this->grammar->rules as $ruleName => $rule) {
                if (isset($terminating[$ruleName])) {
                    continue;
                }

                foreach ($rule->alternatives as $alternative) {
                    if ($this->alternativeTerminates($alternative, $terminating)) {
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
    private function alternativeTerminates(Production $alternative, array $terminating): bool
    {
        foreach ($alternative->references() as $reference) {
            if (!isset($terminating[$reference])) {
                return false;
            }
        }

        return true;
    }
}
