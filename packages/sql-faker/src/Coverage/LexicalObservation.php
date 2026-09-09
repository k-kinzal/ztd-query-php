<?php

declare(strict_types=1);

namespace SqlFaker\Coverage;

use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Spacing\SpacingConstraint;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * Extracts finite definition features and detailed diagnostics without consuming generation choices.
 * @phpstan-type Origin array{id: int, name: string, ancestors: list<int>, rules: list<string>, rewrite: string|null}
 * @phpstan-type Rewrite array{rule: string, offset: int, removed: list<Origin>, inserted: list<Origin>}
 */
final class LexicalObservation
{
    /**
     * Keeps random value text out of cumulative feature IDs while retaining compound structure and version provenance.
     * @return array<string, list<string>>
     */
    public function features(ResolvedOutput $output): array
    {
        $features = ['lexeme' => [], 'compound' => [], 'version-case' => [], 'spacing' => []];
        foreach ($output->candidates as $candidate) {
            $definitions = array_map(static fn (Lexeme $lexeme): string => $lexeme->definition, $candidate->lexemes);
            array_push($features['lexeme'], ...$definitions);
            if (count($definitions) > 1) {
                $features['compound'][] = implode(' + ', $definitions);
            }
            foreach ($candidate->sources() as $source) {
                if (str_starts_with($source, 'version-case:')) {
                    $features['version-case'][] = substr($source, strlen('version-case:'));
                }
            }
        }
        foreach ($output->parts as $part) {
            foreach ($part->spacingRules === [] ? ['default'] : $part->spacingRules as $rule) {
                $features['spacing'][] = $rule . ':' . ($part->separator === '' ? 'join' : 'space');
            }
        }
        return array_map(static fn (array $ids): array => array_values(array_unique($ids)), $features);
    }

    /**
     * Retains every contributing source, including the selected VersionCase.
     * @return array<int, list<string>>
     */
    public function sources(ResolvedOutput $output): array
    {
        $sources = [];
        foreach ($output->candidates as $index => $candidate) {
            $sources[$index] = [];
            foreach ($candidate->sources() as $source) {
                $sources[$index][] = $source;
            }
        }
        return $sources;
    }

    /**
     * Preserves each candidate's pending and internal boundary masks before they are intersected.
     * @return array<int, array{left: array{allowed: int, rules: list<string>}|null, boundaries: array<int, array{allowed: int, rules: list<string>}>}>
     */
    public function conditions(ResolvedOutput $output): array
    {
        $condition = static fn (SpacingConstraint $constraint): array => ['allowed' => $constraint->allowed, 'rules' => $constraint->rules];
        $conditions = [];
        foreach ($output->candidates as $index => $candidate) {
            $conditions[$index] = ['left' => $candidate->left === null ? null : $condition($candidate->left), 'boundaries' => array_map($condition, $candidate->boundaries)];
        }
        return $conditions;
    }

    /**
     * Converts compact rewrite operations to serializable before/after occurrences, preserving intermediate states.
     * @return list<Rewrite>
     */
    public function rewrites(TerminalSequence $sequence): array
    {
        $origin = static fn (TerminalOccurrence $terminal): array => ['id' => $terminal->id, 'name' => $terminal->name,
            'ancestors' => $terminal->ancestors, 'rules' => $terminal->rules, 'rewrite' => $terminal->rewrite];
        return array_map(static fn (array $operation): array => ['rule' => $operation['rule'], 'offset' => $operation['offset'],
            'removed' => array_map($origin, $operation['removed']), 'inserted' => array_map($origin, $operation['inserted'])], $sequence->operations);
    }
}
