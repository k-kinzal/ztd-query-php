<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Operation;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\CallExecutor;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\Native\Invocation;
use Deriver\Evaluation\Call\TypeBinding;
use Deriver\Evaluation\Completion;
use Deriver\Evaluation\InstructionTransfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Value\Operations;
use Deriver\Value\Term;

/**
 * Routes object string conversion through captured source methods and records opaque protocols.
 * @visibility root
 */
final class Conversions
{
    /**
     * @param Machine $machine Shared evaluator
     */
    public function __construct(public readonly Machine $machine)
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
        if ($value->kind === 'object') {
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
            $state->completion = new Completion('throw', new Term('throwable', 'Error'));
            return [$state];
        }
        if (in_array($value->kind, ['closure', 'enum'], true)) {
            $state->completion = new Completion('throw', new Term('throwable', 'Error'));
            return [$state];
        }
        if ($this->object($value)) {
            return $this->boundary($caller, $instruction, $state, 'dynamic-string-conversion');
        }
        if ($value->kind === 'array') {
            $this->machine->context->frontier('PHP_WARNING', $instruction->source, 'array-to-string');
            $state->registers[$instruction->result] = Term::constant('Array', $value->isSecret());
        } else {
            $converted = (new Operations($this->machine->context->configuration->target->floatPrecision))->cast('string', $value);
            if ($converted->kind === 'opaque' && is_string($converted->literal)) {
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
            if ($first->completion->kind !== 'normal') {
                $results[] = $first;
                continue;
            }
            $a = $first->value($instruction->result);
            foreach ($this->string($caller, $instruction, $first, $right) as $second) {
                if ($second->completion->kind === 'normal') {
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
        if ($value->kind === 'constant' || $value->kind === 'array' || $value->kind === 'uninitialized') {
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
        return (new InstructionTransfer($this->machine))->boundary($caller, new Instruction($instruction->id, 'unsupported', $instruction->source, $instruction->result, $instruction->operands, $operation, attributes: ['effects' => 'reachable', 'type' => $operation === 'dynamic-string-conversion' ? 'string' : 'mixed']), $state);
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
            if ($path->completion->kind !== 'normal') {
                $results[] = $path;
                continue;
            }
            $check = (new TypeBinding($this->machine->context))->check($path->value($instruction->result), 'string', $strict);
            (new TypeBinding($this->machine->context))->report($check, $instruction->source, $path);
            if ($check->mayFail) {
                $exception = $path->fork();
                $exception->completion = new Completion('throw', $check->exception());
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
