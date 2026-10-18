<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Operation;

use Deriver\Deriver\Evaluator\Execution\Call\CallExecutor;
use Deriver\Deriver\Evaluator\Execution\Call\Native\Invocation;
use Deriver\Deriver\Evaluator\Execution\Call\TypeBinding;
use Deriver\Deriver\Evaluator\Execution\Completion\Completion;
use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\InstructionTransfer;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;

/**
 * Routes object string conversion through captured source methods and records opaque protocols.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class Conversions
{
    /**
     * @param Evaluator $machine Shared evaluator
     */
    public function __construct(public readonly Evaluator $machine)
    {
    }

    /**
     * Intercepts conversions that can execute PHP methods or emit target diagnostics.
     * @param CallableGraph $caller Current graph
     * @param Instruction $instruction Operator
     * @param State $state Current path
     * @return list<State>|null Transferred paths, or no specialized conversion
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state): ?array
    {
        if (!in_array($instruction->operation, ['cast', 'binary'], true)) {
            return null;
        }
        $left = $state->value($instruction->operands[0] ?? '');
        $right = $state->value($instruction->operands[1] ?? '');
        if ($instruction->operation === 'cast' && strtolower($instruction->name) === 'string') {
            return $this->string($caller, $instruction, $state, $left);
        }
        if ($instruction->operation === 'binary' && $instruction->name === '.') {
            return $this->concat($caller, $instruction, $state, $left, $right);
        }
        if ($instruction->operation === 'cast' && strtolower($instruction->name) !== 'bool' && $this->object($left)) {
            return $this->boundary($caller, $instruction, $state, 'object-cast');
        }
        if ($instruction->operation === 'binary' && in_array($instruction->name, ['==', '!=', '<', '<=', '>', '>=', '<=>'], true) && ($this->object($left) || $this->object($right))) {
            return $this->boundary($caller, $instruction, $state, 'object-comparison');
        }
        return null;
    }

    /**
     * Converts one frozen value while preserving user method effects and exceptions.
     * @param CallableGraph $caller Calling graph
     * @param Instruction $instruction Destination register and provenance
     * @param State $state Current path
     * @param Term $value Frozen operand
     * @return list<State> String results or exceptional completions
     */
    public function string(CallableGraph $caller, Instruction $instruction, State $state, Term $value): array
    {
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Object) {
            $class = $value->attributes['class'] ?? '';
            $symbol = is_string($class) ? (new Dispatch($this->machine->context->program))->method($class, '__toString') : null;
            if ($symbol !== null) {
                $paths = (new CallExecutor($this->machine))->symbol($symbol, [], $state, $instruction, $value, strict: $caller->strict);
                return $this->returned($paths, $instruction, $symbol);
            }
            $native = is_string($class) ? (new Invocation($this->machine))->apply($class, '__toString', [], $state, $instruction, $value, $caller->strict) : null;
            if ($native !== null) {
                return $native;
            }
            $state->completion = new Completion(\Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Throw, new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Error'));
            return [$state];
        }
        if (in_array($value->kind, [\Deriver\Deriver\Evaluator\TermKind::Closure, \Deriver\Deriver\Evaluator\TermKind::Enum], true)) {
            $state->completion = new Completion(\Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Throw, new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Error'));
            return [$state];
        }
        if ($this->object($value)) {
            return $this->boundary($caller, $instruction, $state, 'dynamic-string-conversion');
        }
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Array) {
            $this->machine->context->frontier('PHP_WARNING', $instruction->source, 'array-to-string');
            $state->registers[$instruction->result] = Term::constant('Array');
        } else {
            $converted = (new Operations($this->machine->context->configuration->target->floatPrecision))->cast('string', $value);
            if ($converted->kind === \Deriver\Deriver\Evaluator\TermKind::Opaque && is_string($converted->literal)) {
                $this->machine->context->frontier($converted->literal, $instruction->source, 'string-conversion', [$value], 'string');
            }
            $state->registers[$instruction->result] = $converted;
        }
        return [$state];
    }

    /**
     * Converts each concatenation operand once before forming its string expression.
     * @param CallableGraph $caller Calling graph
     * @param Instruction $instruction Concatenation destination
     * @param State $state Input path
     * @param Term $left Frozen left operand
     * @param Term $right Frozen right operand
     * @return list<State> Concatenations and conversion exceptions
     */
    public function concat(CallableGraph $caller, Instruction $instruction, State $state, Term $left, Term $right): array
    {
        $results = [];
        foreach ($this->string($caller, $instruction, $state, $left) as $first) {
            if ($first->completion->kind !== \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Normal) {
                $results[] = $first;
                continue;
            }
            $a = $first->value($instruction->result);
            foreach ($this->string($caller, $instruction, $first, $right) as $second) {
                if ($second->completion->kind === \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Normal) {
                    $second->registers[$instruction->result] = (new Operations($this->machine->context->configuration->target->floatPrecision))->binary('.', $a, $second->value($instruction->result));
                }
                $results[] = $second;
            }
        }
        return $results;
    }

    /**
     * Checks whether an operand can have object conversion or comparison behavior.
     * @param Term $value Frozen operand
     * @return bool Whether scalar-only semantics have not been established
     */
    public function object(Term $value): bool
    {
        if ($value->kind === \Deriver\Deriver\Evaluator\TermKind::Constant || $value->kind === \Deriver\Deriver\Evaluator\TermKind::Array || $value->kind === \Deriver\Deriver\Evaluator\TermKind::Uninitialized) {
            return false;
        }
        $types = explode('|', (string) ($value->attributes['type'] ?? 'mixed'));
        return array_diff($types, ['int', 'float', 'string', 'bool', 'true', 'false', 'null', 'array']) !== [];
    }

    /**
     * Keeps possible arbitrary method effects behind an explicit language frontier.
     * @param CallableGraph $caller Calling graph
     * @param Instruction $instruction Source operator
     * @param State $state Current path
     * @param string $operation Missing semantic protocol
     * @return list<State> Inclusive normal and exceptional paths
     */
    public function boundary(CallableGraph $caller, Instruction $instruction, State $state, string $operation): array
    {
        return (new InstructionTransfer($this->machine))->boundary($caller, new \Deriver\Deriver\Evaluator\Call\BoundInstruction($instruction->id, 'unsupported', $instruction->source, $instruction->result, $instruction->operands, $operation, attributes: ['effects' => 'reachable', 'type' => $operation === 'dynamic-string-conversion' ? 'string' : 'mixed']), $state);
    }

    /**
     * Enforces PHP's implicit string return contract even without a written return type.
     * @param list<State> $paths Completed __toString invocations
     * @param Instruction $instruction Conversion destination
     * @param string $symbol Source conversion method
     * @return list<State> Valid string results and conversion exceptions
     */
    public function returned(array $paths, Instruction $instruction, string $symbol): array
    {
        $strict = $this->machine->context->program->callable($symbol)->strict ?? false;
        $results = [];
        foreach ($paths as $path) {
            if ($path->completion->kind !== \Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Normal) {
                $results[] = $path;
                continue;
            }
            $check = (new TypeBinding($this->machine->context))->check($path->value($instruction->result), 'string', $strict);
            (new TypeBinding($this->machine->context))->report($check, $instruction->source, $path);
            if ($check->mayFail) {
                $exception = $path->fork();
                $exception->completion = new Completion(\Deriver\Deriver\Evaluator\Execution\Completion\CompletionKind::Throw, $check->exception());
                $results[] = $exception;
            }
            if (!$check->mustFail) {
                $path->registers[$instruction->result] = $check->value;
                $results[] = $path;
            }
        }
        return $results;
    }
}
