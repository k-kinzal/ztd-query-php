<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Call;

use Deriver\Exception\InvalidInputException;

/**
 * Selects the unique maximal evaluator from a deterministic precedence graph.
 * @visibility root
 */
final class Precedence
{
    /**
     * Validates replacement cycles before resolving priorities among remaining evaluators.
     * @param array<string, CallDescriptor> $evaluators Evaluators for one normalized symbol
     * @return string Winning evaluator identifier
     * @throws InvalidInputException If precedence is cyclic or ambiguous
     */
    public function select(array $evaluators): string
    {
        ksort($evaluators);
        $edges = [];
        foreach ($evaluators as $id => $evaluator) {
            $edges[$id] = array_fill_keys(array_intersect($evaluator->replaces, array_keys($evaluators)), true);
        }
        $this->acyclic($edges);
        foreach (array_keys($evaluators) as $via) {
            foreach ($edges as $id => $targets) {
                if (isset($targets[$via])) {
                    $edges[$id] += $edges[$via];
                }
            }
        }
        $replacement = $edges;
        foreach ($evaluators as $id => $evaluator) {
            foreach ($evaluators as $other => $candidate) {
                if (!isset($replacement[$id][$other]) && !isset($replacement[$other][$id]) && $evaluator->priority > $candidate->priority) {
                    $edges[$id][$other] = true;
                }
            }
        }
        $this->acyclic($edges);
        $dominated = [];
        foreach ($edges as $targets) {
            $dominated += $targets;
        }
        $maxima = array_diff_key($evaluators, $dominated);
        if (count($maxima) !== 1) {
            throw new InvalidInputException('EVALUATOR_CONFLICT: multiple maximal evaluators for one symbol.');
        }
        return array_key_first($maxima);
    }

    /**
     * Removes graph leaves until every node is accounted for.
     * @param array<string, array<string, true>> $edges Directed precedence graph
     * @throws InvalidInputException If any cycle remains
     */
    public function acyclic(array $edges): void
    {
        while ($edges !== []) {
            $leaves = array_filter($edges, static fn (array $targets): bool => $targets === []);
            if ($leaves === []) {
                throw new InvalidInputException('EVALUATOR_CONFLICT: cyclic evaluator precedence.');
            }
            $edges = array_diff_key($edges, $leaves);
            foreach ($edges as $id => $targets) {
                $edges[$id] = array_diff_key($targets, $leaves);
            }
        }
    }
}
