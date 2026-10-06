<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Query\ValueQuery;
use Deriver\Result\DerivationResult;
use Deriver\Value\Term;
use JsonException;

/**
 * Observes sink candidates and compares partial candidates with independently observed runtime values.
 * @visibility root
 */
final class Candidates
{
    /**
     * Derives the first argument of the first sink() call in fixture declarations.
     * @param string $source Declarations without an opening tag or the sink function
     * @return DerivationResult Candidates of the sink argument
     * @throws JsonException If captured metadata cannot be encoded
     */
    public static function sink(string $source): DerivationResult
    {
        $session = Analysis::session('<?php function sink($value){} ' . $source);
        return $session->derive(new ValueQuery($session->callsTo('sink')[0]->argument(0)));
    }

    /**
     * Checks whether a derived candidate can denote a concrete runtime value; unknown parts cover anything at their position.
     * @param Term $term Derived candidate
     * @param Term $value Concrete runtime value
     * @return bool Whether the runtime value is among the values the candidate denotes
     */
    public static function covers(Term $term, Term $value): bool
    {
        if ($term->isConcrete()) {
            return $term->native() === $value->native();
        }
        if ($term->kind === 'concat') {
            return $value->kind === 'constant' && is_string($value->literal) && str_starts_with($value->literal, self::prefix($term));
        }
        if ($term->kind !== 'array' || ($term->attributes['open'] ?? false) === true) {
            return true;
        }
        if ($value->kind !== 'array' || array_keys($value->operands) !== array_keys($term->operands)) {
            return false;
        }
        foreach ($term->operands as $key => $element) {
            if (!self::covers($element, $value->operands[$key])) {
                return false;
            }
        }
        return true;
    }

    /**
     * Collects the concrete text before the first unknown part of a concatenation.
     * @param Term $term String expression
     * @return string Known leading text
     */
    public static function prefix(Term $term): string
    {
        $prefix = '';
        $pending = [$term];
        while ($pending !== []) {
            $part = array_pop($pending);
            if ($part->kind === 'concat') {
                array_push($pending, ...array_reverse($part->operands));
                continue;
            }
            $native = $part->isConcrete() ? $part->native() : null;
            if (!is_string($native)) {
                break;
            }
            $prefix .= $native;
        }
        return $prefix;
    }

    /**
     * Lists the observed values of a sink query.
     * @param DerivationResult $result Derived result
     * @return list<Term> Values in outcome order
     */
    public static function values(DerivationResult $result): array
    {
        return array_map(static fn (\Deriver\Result\Alternative $outcome): Term => $outcome->values['value'], $result->normalOutcomes);
    }

    /**
     * Checks that a runtime string is an exact candidate or contained by a widened residual.
     * @param DerivationResult $result Derived result
     * @param string|int $value Runtime value
     * @return bool Whether some outcome includes the value
     */
    public static function contained(DerivationResult $result, string|int $value): bool
    {
        return array_filter(self::values($result), static fn (Term $candidate): bool => (new \Deriver\Value\Lattice())->contains($candidate, Term::constant($value))) !== [];
    }

    /**
     * Enumerates the strings PHP builds from independent two-way letter choices.
     * @param list<string> $letters Lowercase letters, each chosen in lower or upper case
     * @return list<string> Every combination in branch order
     */
    public static function choices(array $letters): array
    {
        $result = [''];
        foreach ($letters as $letter) {
            $result = array_merge(...array_map(static fn (string $prefix): array => [$prefix . $letter, $prefix . strtoupper($letter)], $result));
        }
        return $result;
    }
}
