<?php

declare(strict_types=1);

namespace Deriver\Model\Compilation;

use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Signature;
use Deriver\Model\State\StateSlot;

/**
 * Validates declarative opcodes, operand counts, and declared state footprints.
 * @visibility root
 */
final class PlanValidation
{
    /**
     * @var list<string> Observed state reads.
     */
    public array $reads = [];
    /**
     * @var list<string> Observed state writes.
     */
    public array $writes = [];
    /**
     * @var list<string> Contract violations.
     */
    public array $errors = [];
    /**
     * Plan-node counter for deterministic registration limits.
     */
    public int $nodes = 0;

    /**
     * Signature whose external reference effects require declared footprints.
     */
    public ?Signature $signature = null;

    /**
     * Validates a finite plan before lowering it into executable IR.
     * @param SemanticPlan $plan Trusted declarative semantics
     * @param Signature|null $signature External callable contract
     * @param array<string, StateSlot>|null $state Registered state lifecycle contracts
     * @return list<string> Detected violations
     */
    public function validate(SemanticPlan $plan, ?Signature $signature = null, ?array $state = null): array
    {
        $this->signature = $signature;
        $this->actions($plan->actions);
        if ($signature?->byReference === true && !$this->completes($plan->actions)) {
            $this->errors[] = 'missing-reference-completion';
        }
        if (array_diff($this->reads, $plan->reads) !== []) {
            $this->errors[] = 'undeclared-state-read';
        }
        if (array_diff($this->writes, $plan->writes) !== []) {
            $this->errors[] = 'undeclared-state-write';
        }
        foreach ([...$this->reads, ...$this->writes] as $slot) {
            if ($state !== null && !str_starts_with($slot, 'parameter:') && !isset($state[$slot])) {
                $this->errors[] = 'unregistered-state-slot:' . $slot;
            }
        }
        return array_values(array_unique($this->errors));
    }

    /**
     * Checks action arities and ordered nested choices.
     * @param list<Action> $actions Declarative sequence
     */
    public function actions(array $actions): void
    {
        foreach ($actions as $action) {
            if (++$this->nodes > 20000) {
                $this->errors[] = 'plan-node-budget';
                return;
            }
            $arity = ['return' => 1, 'throw' => 1, 'state-write' => 2, 'write-parameter' => 1, 'choice' => 1, 'callback' => -1, 'invoke' => 1, 'allocate' => 1, 'location-write' => 1, 'alias' => 0, 'return-reference' => 0, 'havoc' => 0][$action->operation] ?? -2;
            if ($arity === -2 || ($arity >= 0 && count($action->operands) !== $arity) || ($arity === -1 && $action->operands === [])) {
                $this->errors[] = 'action-arity-or-opcode:' . $action->operation;
                continue;
            }
            (new PlanFootprints($this))->action($action);
            if ($action->operation === 'state-write') {
                $this->writes[] = $action->name;
            }
            if ($action->operation === 'write-parameter' && !str_starts_with($action->name, '@')) {
                $this->writes[] = 'parameter:' . $action->name;
            }
            foreach ($action->operands as $expression) {
                $this->expression($expression);
            }
            $this->actions($action->yes);
            $this->actions($action->no);
        }
    }

    /**
     * Checks that a reference-returning model explicitly completes every acyclic branch.
     * @param list<Action> $actions Ordered sequence
     * @return bool Whether normal fallthrough is impossible
     */
    public function completes(array $actions): bool
    {
        foreach ($actions as $action) {
            if (++$this->nodes > 20000) {
                $this->errors[] = 'plan-node-budget';
                return false;
            }
            if (in_array($action->operation, ['return', 'return-reference', 'throw'], true)) {
                return true;
            }
            if ($action->operation === 'choice' && $this->completes($action->yes) && $this->completes($action->no)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checks pure expressions without invoking their intrinsic implementations.
     * @param Expression $expression Declarative expression
     */
    public function expression(Expression $expression): void
    {
        if (++$this->nodes > 20000) {
            $this->errors[] = 'plan-node-budget';
            return;
        }
        $arity = ['parameter' => 0, 'constant' => 0, 'external' => 0, 'state' => 1, 'binary' => 2, 'unary' => 1, 'cast' => 1, 'array-read' => 2, 'array-set' => 3, 'intrinsic' => -1, 'location-read' => 0][$expression->operation] ?? -2;
        if ($arity === -2 || ($arity >= 0 && count($expression->operands) !== $arity)) {
            $this->errors[] = 'expression-arity-or-opcode:' . $expression->operation;
            return;
        }
        if ($expression->operation === 'location-read') {
            if ($expression->location === null) {
                $this->errors[] = 'missing-location';
            } else {
                (new PlanFootprints($this))->location($expression->location, false);
            }
        }
        if ($expression->operation === 'constant' && $expression->constant === null) {
            $this->errors[] = 'missing-constant';
        }
        if ($expression->operation === 'state') {
            $this->reads[] = $expression->name;
        }
        foreach ($expression->operands as $operand) {
            $this->expression($operand);
        }
    }
}
