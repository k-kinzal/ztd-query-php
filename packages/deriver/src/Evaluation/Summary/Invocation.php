<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Summary;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\Evaluation\Demand\Key;
use Deriver\Evaluation\State;
use Deriver\Value\Identity;

/**
 * Captures only relevant local inputs for a proven isolated callable specialization.
 * @visibility root
 */
final class Invocation
{
    /**
     * Builds a demand without depending on unrelated caller cells or allocation counters.
     * @param CallableGraph $body Source callable
     * @param State $entry Bound local inputs and entry constraints
     * @param list<string> $history Caller history, limited to the last two call sites
     * @return Key Specialization identity
     */
    public function key(CallableGraph $body, State $entry, array $history): Key
    {
        $identity = new Identity();
        $inputs = [];
        foreach ($entry->locals as $name => $location) {
            $inputs[$name] = $identity->key($entry->memory->read($location));
        }
        ksort($inputs);
        $constraints = [];
        foreach ($entry->constraints as $name => $constraint) {
            $constraints[$name] = [$constraint['min'], $constraint['max'], $constraint['equal'] === null ? null : $identity->key($constraint['equal']), array_map($identity->key(...), $constraint['excluded'])];
        }
        ksort($constraints);
        $guards = $entry->guard;
        ksort($guards);
        return new Key($body->source->snapshotId, (new CallableIdentity())->key($body->symbol), 'entry', 'value', 'completion-and-locals', hash('sha256', serialize([$inputs, array_slice($history, -2)])), 'isolated-locals', hash('sha256', serialize([$guards, $constraints])));
    }
}
