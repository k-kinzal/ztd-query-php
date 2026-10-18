<?php

declare(strict_types=1);

namespace Deriver\Deriver\Selection;

use Deriver\Deriver\Evaluator\Builtin\Library;
use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Request\Observation\Observation;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\ExpressionRef;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Program;
use Deriver\Deriver\Lowerer\Hierarchy\Dispatch;
use Deriver\Deriver\Sources\SourceRef;

/**
 * Projects source call instructions into stable public observation references.
 * @visibility parent
 * @visibility Deriver\Deriver
 * @visibility Deriver\Deriver
 */
final class CallObservations
{
    /**
     * @param Program $program Captured callable graphs
     * @param Evaluators|null $evaluators Captured evaluator selection for namespace fallback
     */
    public function __construct(public readonly Program $program, public readonly ?Evaluators $evaluators = null)
    {
    }

    /**
     * Finds statically named invocations and object creations in the captured world.
     * @param string $symbol Function or method selector, `Class::__construct`, or `*`
     * @return list<Observation> Matching call sites
     */
    public function find(string $symbol): array
    {
        $result = [];
        foreach ($this->program->callOwners($symbol) as $owner) {
            $callable = $this->program->callable($owner);
            if ($callable === null) {
                continue;
            }
            array_push($result, ...$this->within($callable, $symbol));
        }
        usort($result, static fn (Observation $a, Observation $b): int => [$a->source->path, $a->source->start, $a->callable] <=> [$b->source->path, $b->source->start, $b->callable]);
        return $result;
    }
    /**
     * Collects definitions before looking at calls, independent of block allocation order.
     * @param CallableGraph $callable Captured owner
     * @param string $selector Requested function or method name, `Class::__construct`, or `*`
     * @return list<Observation> Matching occurrences
     */
    public function within(CallableGraph $callable, string $selector): array
    {
        $constants = [];
        $sources = [];
        $calls = [];
        foreach ($callable->blocks as $block) {
            foreach ($block->instructions as $instruction) {
                $sources[$instruction->result] = $instruction->source;
                if ($instruction->constant !== null) {
                    $constants[$instruction->result] = $instruction->constant;
                }
                if (in_array($instruction->operation, ['invoke', 'invoke-method', 'invoke-static', 'new'], true)) {
                    $calls[] = $instruction;
                }
            }
        }
        $result = [];
        $identity = new CallableIdentity();
        foreach ($calls as $instruction) {
            $index = in_array($instruction->operation, ['invoke', 'new'], true) ? 0 : 1;
            $name = $constants[$instruction->operands[$index] ?? '']->literal ?? null;
            if (!is_string($name)) {
                if ($selector === '*' && $instruction->operation !== 'new') {
                    $result[] = $this->observation($callable->symbol, $instruction, '', $sources);
                }
                continue;
            }
            $name = match ($instruction->operation) {
                'invoke' => $this->name($name, $instruction),
                'new' => $this->className($name, $instruction),
                default => $name,
            };
            $selected = $instruction->operation === 'new' ? $name . '::__construct' : $name;
            if ($selector === '*' || $identity->key($selected) === $identity->key($selector)) {
                $result[] = $this->observation($callable->symbol, $instruction, $name, $sources);
            }
        }
        return $result;
    }

    /**
     * Prefers the namespaced declaration or evaluator and otherwise applies the recorded global fallback.
     * @param string $symbol Namespaced function name as written
     * @param Instruction $instruction Call carrying the `fallback` attribute
     * @return string Selected callable symbol
     */
    public function name(string $symbol, Instruction $instruction): string
    {
        $fallback = $instruction->attributes['fallback'] ?? '';
        if ($this->declared($symbol) || !is_string($fallback) || $fallback === '') {
            return $symbol;
        }
        $standard = ($this->evaluators->standardEvaluators ?? true) ? (new Library())->evaluator($fallback) : null;
        return $this->declared($fallback) || $standard !== null ? $fallback : $symbol;
    }

    /**
     * Reports whether the captured source or the registered evaluators declare the callable.
     */
    public function declared(string $symbol): bool
    {
        return $this->program->callable($symbol) !== null || isset($this->evaluators?->evaluators[(new CallableIdentity())->key($symbol)]);
    }

    /**
     * Resolves the lexical `self` and `parent` of a creation; late-bound `static` stays as written.
     * @param string $name Created class as compiled
     * @param Instruction $instruction Creation with its lexical class scope
     * @return string Created class name without a leading separator
     */
    public function className(string $name, Instruction $instruction): string
    {
        $scope = $instruction->attributes['scope'] ?? '';
        if (($instruction->attributes['literal-class'] ?? false) !== true || !is_string($scope) || !in_array(strtolower($name), ['self', 'parent'], true)) {
            return ltrim($name, '\\');
        }
        $resolved = (new Dispatch($this->program))->className($name, $scope, '');
        return $resolved === '' ? $name : $resolved;
    }

    /**
     * Projects evaluated operand references without resolving them a second time.
     * @param string $owner Declaring callable
     * @param Instruction $instruction Invocation
     * @param string $name Resolved function, method, or created class spelling
     * @param array<string, SourceRef> $sources Register source locations
     * @return Observation Queryable occurrence
     */
    public function observation(string $owner, Instruction $instruction, string $name, array $sources): Observation
    {
        $arguments = [];
        foreach ($instruction->arguments as $position => $argument) {
            $reference = new ExpressionRef($sources[$argument->register] ?? $instruction->source, $owner, $argument->register);
            $arguments[$position] = $reference;
            if ($argument->name !== null) {
                $arguments[$argument->name] = $reference;
            }
        }
        $receiver = in_array($instruction->operation, ['invoke', 'new'], true) ? null : new ExpressionRef($sources[$instruction->operands[0]] ?? $instruction->source, $owner, $instruction->operands[0]);
        return new Observation($instruction->source, $owner, $instruction->id, $name, $arguments, new ExpressionRef($instruction->source, $owner, $instruction->result), $receiver, $instruction->operation);
    }

}
