<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Offset;

use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\Context;
use Deriver\Evaluation\State;
use Deriver\Value\Term;

/**
 * Enumerates finite array slots under correlated key predicates, retaining absence.
 * @visibility root
 */
final class ReadCandidates
{
    /**
     * @param Context $context Query constraints and budgets
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Selects each possible slot without treating unknown keys as the first key.
     * @param State $state Original read state
     * @param Instruction $instruction Destination and source
     * @param Term $value Symbolic read from a closed array
     * @return list<State> Slot alternatives and the missing-key path
     */
    public function apply(State $state, Instruction $instruction, Term $value): array
    {
        [$array, $key] = $value->operands;
        $result = [];
        if (($value->attributes['mayRejectKey'] ?? false) === true) {
            $failure = $state->fork();
            $failure->completion = new Completion('throw', new Term('throwable', 'TypeError'));
            $result[] = $failure;
        }
        if ($array->kind !== 'array' || ($array->attributes['open'] ?? false) === true || count($array->operands) >= $this->context->query->budget()->partitions) {
            $state->registers[$instruction->result] = $value;
            return [$state, ...$result];
        }
        $missing = $state->fork();
        $absent = true;
        foreach (array_keys($array->operands) as $index) {
            $test = new Term('binary', '===', [$key, Term::constant($index)], ['type' => 'bool']);
            $path = $missing->fork();
            if ((new Constraints($this->context))->assume($path, $test, true)) {
                $path->registers[$instruction->result] = $state->memory->element($array, $index, $key->isSecret());
                $result[] = $path;
            }
            $absent = (new Constraints($this->context))->assume($missing, $test, false);
            if (!$absent) {
                break;
            }
        }
        if ($absent) {
            $silent = ($value->attributes['silent'] ?? false) === true;
            if (!$silent) {
                (new Strings($this->context))->warning($instruction);
            }
            $missing->registers[$instruction->result] = $silent ? new Term('uninitialized') : Term::constant(null, $key->isSecret() || $array->isSecret());
            $result[] = $missing;
        }
        return $result;
    }
}
