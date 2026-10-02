<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Operation;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Model\Builtin\TypePredicates;
use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Joins known array shapes without losing string fragments around unknown elements.
 * @visibility root
 */
final class StringJoining
{
    /**
     * @param Machine $machine Shared conversions and method calls
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * @param CallableGraph $caller Model frame
     * @param Instruction $instruction Intrinsic destination
     * @param State $state Bound inputs
     * @param list<Term> $values Separator and array
     * @return list<State>|null Converted results, or a case for the ordinary intrinsic
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state, array $values): ?array
    {
        [$separator, $array] = $values;
        if ($array->kind === 'constant' && $array->literal === null) {
            [$separator, $array] = [Term::constant(''), $separator];
        }
        if ($array->kind !== 'array' || ($array->attributes['open'] ?? false) === true || (new TypePredicates())->apply('is_string', $separator)->literal !== true) {
            return null;
        }
        $paths = [[$state, Term::constant('')]];
        $failures = [];
        $first = true;
        foreach ($array->operands as $part) {
            $next = [];
            foreach ($paths as [$path, $prefix]) {
                foreach ((new Conversions($this->machine))->string($caller, $instruction, $path, $path->memory->dereference($part)) as $converted) {
                    if ($converted->completion->kind !== 'normal') {
                        $failures[] = $converted;
                        continue;
                    }
                    $semantics = new Operations($this->machine->context->configuration->target->floatPrecision);
                    $left = $first ? $prefix : $semantics->binary('.', $prefix, $separator);
                    $value = $semantics->binary('.', $left, $converted->value($instruction->result));
                    $converted->registers[$instruction->result] = $value;
                    $next[] = [$converted, $value];
                }
            }
            $paths = $next;
            $first = false;
        }
        foreach ($paths as [$path, $value]) {
            $path->registers[$instruction->result] = $array->isSecret() ? new Term($value->kind, $value->literal, $value->operands, $value->attributes, true) : $value;
            $failures[] = $path;
        }
        return $failures;
    }
}
