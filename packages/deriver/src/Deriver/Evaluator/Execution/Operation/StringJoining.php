<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Operation;

use Deriver\Deriver\Evaluator\Builtin\TypePredicates;
use Deriver\Deriver\Evaluator\Execution\Call\PassedArgument;
use Deriver\Deriver\Evaluator\Execution\Call\UnknownCall;
use Deriver\Deriver\Evaluator\Execution\Constraints;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Scalar\Arrays;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Joins known array shapes and known heads of symbolic merges without losing string fragments around unknown elements.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class StringJoining
{
    /**
     * @param Evaluator $machine Shared conversions and method calls
     */
    public function __construct(public readonly Evaluator $machine)
    {
    }

    /**
     * @param CallableGraph $caller Evaluator frame
     * @param Instruction $instruction Intrinsic destination
     * @param State $state Bound inputs
     * @param list<Term> $values Separator and array
     * @return list<State>|null Converted results, or a case for the ordinary intrinsic
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state, array $values): ?array
    {
        [$separator, $array] = $values;
        if ($array->kind === \Deriver\Deriver\Evaluator\TermKind::Constant && $array->literal === null) {
            [$separator, $array] = [Term::constant(''), $separator];
        }
        if ((new TypePredicates())->apply('is_string', $separator)->literal !== true) {
            return null;
        }
        $tail = (new Arrays())->tail($array);
        if ($tail !== null) {
            return $this->suffix($caller, $instruction, $state, $separator, $tail);
        }
        $split = (new Arrays())->split($array);
        if ($split !== null) {
            return $this->partial($caller, $instruction, $state, $separator, $split);
        }
        if ($array->kind !== \Deriver\Deriver\Evaluator\TermKind::Array || ($array->attributes['open'] ?? false) === true) {
            return null;
        }
        return $this->join($caller, $instruction, $state, $separator, $array);
    }

    /**
     * Converts each known element in order, keeping user conversion effects and failures.
     * @param CallableGraph $caller Evaluator frame
     * @param Instruction $instruction Intrinsic destination
     * @param State $state Bound inputs
     * @param Term $separator String separator
     * @param Term $array Closed array shape
     * @return list<State> Joined results and conversion exceptions
     */
    public function join(CallableGraph $caller, Instruction $instruction, State $state, Term $separator, Term $array): array
    {
        $paths = [[$state, Term::constant('')]];
        $failures = [];
        $first = true;
        foreach ($array->operands as $part) {
            $next = [];
            foreach ($paths as [$path, $prefix]) {
                foreach ((new Conversions($this->machine))->string($caller, $instruction, $path, $path->memory->dereference($part)) as $converted) {
                    if ($converted->completion->kind !== \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Normal) {
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
            $path->registers[$instruction->result] = $value;
            $failures[] = $path;
        }
        return $failures;
    }

    /**
     * Joins the known head exactly when the appended source is empty, and otherwise keeps it before an explicit residual for the source.
     * @param CallableGraph $caller Evaluator frame
     * @param Instruction $instruction Intrinsic destination
     * @param State $state Bound inputs
     * @param Term $separator String separator
     * @param array{Term, Term} $split Closed integer-keyed head and appended source
     * @return list<State> Exact, partial, and exceptional results
     */
    public function partial(CallableGraph $caller, Instruction $instruction, State $state, Term $separator, array $split): array
    {
        [$head, $tail] = $split;
        $semantics = new Operations($this->machine->context->configuration->target->floatPrecision);
        $empty = $semantics->binary('===', $tail, Term::array([]));
        $results = [];
        foreach ($this->join($caller, $instruction, $state, $separator, $head) as $path) {
            if ($path->completion->kind !== \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Normal) {
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
            foreach ((new UnknownCall($this->machine->context))->apply($remainder, $instruction, [new PassedArgument($separator), new PassedArgument($tail)], null, 'UNSUPPORTED_EVALUATOR_CASE', 'string') as $joined) {
                if ($joined->completion->kind === \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Normal) {
                    $value = $semantics->binary('.', $prefix, $joined->value($instruction->result));
                    $joined->registers[$instruction->result] = $value;
                }
                $results[] = $joined;
            }
        }
        return $results;
    }
    /**
     * Keeps known trailing entries after a symbolic unpack, including the empty-prefix case.
     * @param CallableGraph $caller Evaluator frame
     * @param Instruction $instruction Intrinsic destination
     * @param State $state Bound inputs
     * @param Term $separator String separator
     * @param array{Term, Term} $split Unknown prefix and known suffix
     * @return list<State> Exact, partial, and exceptional paths
     */
    public function suffix(CallableGraph $caller, Instruction $instruction, State $state, Term $separator, array $split): array
    {
        [$prefix, $tail] = $split;
        $semantics = new Operations($this->machine->context->configuration->target->floatPrecision);
        $empty = $semantics->binary('===', $prefix, Term::array([]));
        $results = [];
        $missing = $state->fork();
        if ((new Constraints($this->machine->context))->assume($missing, $empty, true)) {
            array_push($results, ...$this->join($caller, $instruction, $missing, $separator, $tail));
        }
        if (!(new Constraints($this->machine->context))->assume($state, $empty, false)) {
            return $results;
        }
        foreach ((new UnknownCall($this->machine->context))->apply($state, $instruction, [new PassedArgument($prefix)], null, 'UNSUPPORTED_EVALUATOR_CASE', 'string') as $path) {
            if ($path->completion->kind !== \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Normal) {
                $results[] = $path;
                continue;
            }
            $text = $semantics->binary('.', $path->value($instruction->result), $separator);
            foreach ($this->join($caller, $instruction, $path, $separator, $tail) as $joined) {
                if ($joined->completion->kind === \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Normal) {
                    $joined->registers[$instruction->result] = $semantics->binary('.', $text, $joined->value($instruction->result));
                }
                $results[] = $joined;
            }
        }
        return $results;
    }

}
