<?php

declare(strict_types=1);

namespace Tests\Fake\Oracle;

use Closure;
use Deriver\Project\EntryPoint;
use Deriver\Query\QueryScope;
use Deriver\Query\ReturnQuery;
use Deriver\Value\Term;
use JsonException;
use Tests\Fake\Analysis;

/**
 * Checks concrete runtime membership and minimizes counterexamples within the generated grammar.
 * @visibility root
 */
final class StateAgreement
{
    /**
     * Collects comparable public outcomes without executing the source in the analyzer.
     * @param string $source Trusted generated source
     * @param int $input Concrete generated input
     * @return list<string> Interpreted return, exception, and heap alternatives
     * @throws JsonException If fixture observations cannot be encoded
     */
    public static function derived(string $source, int $input): array
    {
        $result = Analysis::session($source)->derive(new ReturnQuery('target', QueryScope::fromEntrypoints([new EntryPoint('target', [Term::constant($input)])])));
        $outcomes = [];
        foreach ($result->normalOutcomes as $outcome) {
            $outcomes[] = (new DerivedState($outcome->storage))->envelope($outcome->values['return'], '');
        }
        foreach ($result->exceptionalOutcomes as $outcome) {
            $class = $outcome->exception->attributes['class'] ?? $outcome->exception->literal;
            if (is_string($class)) {
                $outcomes[] = (new DerivedState($outcome->storage))->envelope(Term::constant(null), $class);
            }
        }
        return $outcomes;
    }

    /**
     * Checks value, completion, property updates, sharing, cycles, and referenced array state.
     * @param list<string> $operations Generated grammar statements
     * @param int $input Concrete fixture input
     * @return bool Whether the independently observed execution is represented
     * @throws JsonException If fixture observations cannot be encoded
     */
    public static function agrees(array $operations, int $input): bool
    {
        $source = StatePrograms::source($operations);
        return in_array(RuntimeState::observe($source, $input), self::derived($source, $input), true);
    }

    /**
     * Removes statements until no remaining single deletion preserves a counterexample.
     * @param list<string> $operations Failing generated program
     * @param int $input Fixed concrete input
     * @param Closure(list<string>): bool|null $fails Optional deterministic counterexample predicate for shrinker contract tests
     * @return list<string> One-deletion-minimal valid fixture operations
     * @throws JsonException If fixture observations cannot be encoded
     */
    public static function shrink(array $operations, int $input, ?Closure $fails = null): array
    {
        do {
            $changed = false;
            foreach (array_keys($operations) as $index) {
                $candidate = $operations;
                unset($candidate[$index]);
                $candidate = array_values($candidate);
                if ($fails === null ? !self::agrees($candidate, $input) : $fails($candidate)) {
                    $operations = $candidate;
                    $changed = true;
                    break;
                }
            }
        } while ($changed);
        return $operations;
    }
}
