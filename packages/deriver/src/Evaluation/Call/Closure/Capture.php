<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call\Closure;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Call\CallableCheck;
use Deriver\Evaluation\Call\Dispatch;
use Deriver\Evaluation\Call\MethodInvocation;
use Deriver\Evaluation\Call\Preparation\Transfer;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Value\Term;

/**
 * Validates first-class callable acquisition and freezes its receiver and access scope.
 * @visibility root
 */
final class Capture
{
    /**
     * @param Machine $machine Source and model call world
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * Checks access at acquisition, including early unknown-target exceptions.
     * @param CallableGraph $caller Acquiring scope
     * @param Instruction $instruction First-class callable syntax
     * @param State $state State after evaluating receiver and name
     * @return list<State> Captured closures and acquisition errors
     */
    public function apply(CallableGraph $caller, Instruction $instruction, State $state): array
    {
        $operation = $instruction->operation === 'callable' ? 'invoke' : (($instruction->attributes['static'] ?? false) === true ? 'invoke-static' : 'invoke-method');
        $prototype = new Instruction($instruction->id, 'call-prepare', $instruction->source, $instruction->result, $instruction->operands, attributes: [...$instruction->attributes, 'call-operation' => $operation]);
        $paths = (new Transfer($this->machine))->apply($caller, $prototype, $state);
        foreach ($paths as $path) {
            if ($path->completion->kind !== 'normal') {
                continue;
            }
            $signature = $path->callTargets[$instruction->result]->signature;
            if ($signature === null) {
                $this->machine->context->frontier('OPEN_DISPATCH', $instruction->source, 'callable-acquisition');
            }
            $target = $instruction->operation === 'callable' ? $path->value($instruction->operands[0]) : new Term('callable-method', operands: [$path->value($instruction->operands[0]), $path->value($instruction->operands[1])]);
            if ($target->kind === 'closure') {
                $path->registers[$instruction->result] = $target;
                continue;
            }
            $target = $this->target($caller, $path, $target, $signature?->symbol, ($instruction->attributes['literal-class'] ?? true) === true);
            $captures = ['target' => $target];
            if (isset($path->locals['this'])) {
                $captures['this'] = $path->memory->read($path->locals['this']);
            }
            $path->registers[$instruction->result] = new Term('closure', operands: $captures, attributes: ['first-class' => true, 'identity' => $path->memory->fresh('closure'), 'scope' => $caller->className, 'late-static' => $target->attributes['late-static'] ?? $path->lateStaticClass]);
        }
        return $paths;
    }

    /**
     * Resolves namespace fallback and relative class names at acquisition time.
     * @param CallableGraph $caller Acquiring scope
     * @param State $state Captured receiver context
     * @param Term $target Evaluated callable expression
     * @param string|null $symbol Resolved function signature
     * @param bool $literal Whether a static class operand uses literal syntax
     * @return Term Frozen function or method target
     */
    public function target(CallableGraph $caller, State $state, Term $target, ?string $symbol, bool $literal = true): Term
    {
        if ($target->kind === 'constant' && is_string($target->literal)) {
            if (!str_contains($target->literal, '::')) {
                return Term::constant($symbol ?? $target->literal);
            }
            [$class, $method] = explode('::', $target->literal, 2);
            $target = new Term('callable-method', operands: [Term::constant($class), Term::constant($method)]);
        } elseif ($target->kind === 'object') {
            $target = new Term('callable-method', operands: [$target, Term::constant('__invoke')]);
        }
        if (!(new CallableCheck($this->machine->context))->pair($target)) {
            return $target;
        }
        $receiver = $state->memory->dereference($target->operands[0]);
        $static = $receiver->kind === 'constant';
        $name = $static ? $receiver->literal : ($receiver->attributes['class'] ?? $receiver->attributes['type'] ?? '');
        $name = is_string($name) ? $name : '';
        $class = $literal ? (new Dispatch($this->machine->context->program))->className($name, $caller->className, $state->lateStaticClass) : ltrim($name, '\\');
        $calledClass = (new MethodInvocation($this->machine))->calledClass($receiver, $state, $class, $static);
        return new Term('callable-method', operands: [$static ? Term::constant($class) : $receiver, $state->memory->dereference($target->operands[1])], attributes: ['bound-callable' => true, 'late-static' => $calledClass]);
    }
}
