<?php

declare(strict_types=1);

namespace Deriver\Model\Registration;

use Deriver\Exception\InvalidInputException;
use Deriver\Model\ModelDescriptor;

/**
 * Selects the unique maximal model from a deterministic precedence graph.
 * @visibility root
 */
final class ModelPrecedence
{
    /**
     * Validates replacement cycles before resolving priorities among remaining models.
     * @param array<string, ModelDescriptor> $models Models for one normalized symbol
     * @return string Winning model identifier
     * @throws InvalidInputException If precedence is cyclic or ambiguous
     */
    public function select(array $models): string
    {
        ksort($models);
        $edges = [];
        foreach ($models as $id => $model) {
            $edges[$id] = array_fill_keys(array_intersect($model->replaces, array_keys($models)), true);
        }
        $this->acyclic($edges);
        foreach (array_keys($models) as $via) {
            foreach ($edges as $id => $targets) {
                if (isset($targets[$via])) {
                    $edges[$id] += $edges[$via];
                }
            }
        }
        $replacement = $edges;
        foreach ($models as $id => $model) {
            foreach ($models as $other => $candidate) {
                if (!isset($replacement[$id][$other]) && !isset($replacement[$other][$id]) && $model->priority > $candidate->priority) {
                    $edges[$id][$other] = true;
                }
            }
        }
        $this->acyclic($edges);
        $dominated = [];
        foreach ($edges as $targets) {
            $dominated += $targets;
        }
        $maxima = array_diff_key($models, $dominated);
        if (count($maxima) !== 1) {
            throw new InvalidInputException('MODEL_CONFLICT: multiple maximal models for one symbol.');
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
                throw new InvalidInputException('MODEL_CONFLICT: cyclic model precedence.');
            }
            $edges = array_diff_key($edges, $leaves);
            foreach ($edges as $id => $targets) {
                $edges[$id] = array_diff_key($targets, $leaves);
            }
        }
    }
}
