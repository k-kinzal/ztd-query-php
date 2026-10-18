<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Offset;

use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Constraints;
use Deriver\Deriver\Evaluator\Execution\Context;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Scalar\Arrays;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Enumerates finite array slots under correlated key predicates, retaining absence.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
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
        $head = (new Arrays())->head($array);
        $candidates = $head ?? $array;
        $result = [];
        if (($value->attributes['mayRejectKey'] ?? false) === true) {
            $failure = $state->fork();
            $failure->completion = new Completion(\Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Throw, new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'TypeError'));
            $result[] = $failure;
        }
        if ($key->kind === \Deriver\Deriver\Evaluator\TermKind::Constant || $candidates->kind !== \Deriver\Deriver\Evaluator\TermKind::Array || ($candidates->attributes['open'] ?? false) === true || count($candidates->operands) >= $this->context->query->budget()->partitions) {
            $state->registers[$instruction->result] = $value;
            return [$state, ...$result];
        }
        $missing = $state->fork();
        $absent = true;
        foreach (array_keys($candidates->operands) as $index) {
            $test = new Term(\Deriver\Deriver\Evaluator\TermKind::Binary, '===', [$key, Term::constant($index)], ['type' => 'bool']);
            $path = $missing->fork();
            if ((new Constraints($this->context))->assume($path, $test, true)) {
                $path->registers[$instruction->result] = $state->memory->element($candidates, $index);
                $result[] = $path;
            }
            $absent = (new Constraints($this->context))->assume($missing, $test, false);
            if (!$absent) {
                break;
            }
        }
        if ($absent && $head !== null) {
            $missing->registers[$instruction->result] = $value;
            $result[] = $missing;
            return $result;
        }
        if ($absent) {
            $silent = ($value->attributes['silent'] ?? false) === true;
            if (!$silent) {
                (new Strings($this->context))->warning($instruction);
            }
            $missing->registers[$instruction->result] = $silent ? new Term(\Deriver\Deriver\Evaluator\TermKind::Uninitialized) : Term::constant(null);
            $result[] = $missing;
        }
        return $result;
    }
}
