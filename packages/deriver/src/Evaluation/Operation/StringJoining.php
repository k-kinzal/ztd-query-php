<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Operation;

use Deriver\Constraint\Constraints;
use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\PassedArgument;
use Deriver\Evaluation\Call\UnknownCall;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Model\Builtin\TypePredicates;
use Deriver\Value\Arrays;
use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Joins known array shapes and known heads of symbolic merges without losing string fragments around unknown elements.
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
        if ((new TypePredicates())->apply('is_string', $separator)->literal !== true) {
            return null;
        }
        $split = (new Arrays())->split($array);
        if ($split !== null) {
            return $this->partial($caller, $instruction, $state, $separator, $split, $array->isSecret());
        }
        if ($array->kind !== 'array' || ($array->attributes['open'] ?? false) === true) {
            return null;
        }
        return $this->join($caller, $instruction, $state, $separator, $array, $array->isSecret());
    }

    /**
     * Converts each known element in order, keeping user conversion effects and failures.
     * @param CallableGraph $caller Model frame
     * @param Instruction $instruction Intrinsic destination
     * @param State $state Bound inputs
     * @param Term $separator String separator
     * @param Term $array Closed array shape
     * @param bool $secret Whether the joined value is confidential
     * @return list<State> Joined results and conversion exceptions
     */
    public function join(CallableGraph $caller, Instruction $instruction, State $state, Term $separator, Term $array, bool $secret): array
    {
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
            $path->registers[$instruction->result] = $secret ? new Term($value->kind, $value->literal, $value->operands, $value->attributes, true) : $value;
            $failures[] = $path;
        }
        return $failures;
    }

    /**
     * Joins the known head exactly when the appended source is empty, and otherwise keeps it before an explicit residual for the source.
     * @param CallableGraph $caller Model frame
     * @param Instruction $instruction Intrinsic destination
     * @param State $state Bound inputs
     * @param Term $separator String separator
     * @param array{Term, Term} $split Closed integer-keyed head and appended source
     * @param bool $secret Whether the joined value is confidential
     * @return list<State> Exact, partial, and exceptional results
     */
    public function partial(CallableGraph $caller, Instruction $instruction, State $state, Term $separator, array $split, bool $secret): array
    {
        [$head, $tail] = $split;
        $semantics = new Operations($this->machine->context->configuration->target->floatPrecision);
        $empty = $semantics->binary('===', $tail, Term::array([]));
        $results = [];
        foreach ($this->join($caller, $instruction, $state, $separator, $head, $secret) as $path) {
            if ($path->completion->kind !== 'normal') {
                $results[] = $path;
                continue;
            }
            $prefix = $semantics->binary('.', $path->value($instruction->result), $separator);
            $remainder = $path->fork();
            if ((new Constraints($this->machine->context))->assume($path, $empty, true)) {
                $results[] = $path;
            }
            if (!(new Constraints($this->machine->context))->assume($remainder, $empty, false)) {
                continue;
            }
            foreach ((new UnknownCall($this->machine->context))->apply($remainder, $instruction, [new PassedArgument($separator), new PassedArgument($tail)], null, 'UNSUPPORTED_MODEL_CASE', 'string') as $joined) {
                if ($joined->completion->kind === 'normal') {
                    $value = $semantics->binary('.', $prefix, $joined->value($instruction->result));
                    $joined->registers[$instruction->result] = $secret ? new Term($value->kind, $value->literal, $value->operands, $value->attributes, true) : $value;
                }
                $results[] = $joined;
            }
        }
        return $results;
    }
}
