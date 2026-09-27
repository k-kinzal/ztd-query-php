<?php

declare(strict_types=1);

namespace Deriver\Model\Compilation;

use Deriver\Model\Binding\LocationRef;
use Deriver\Model\Plan\Action;

/**
 * Validates model addresses and records explicit reads, writes, and reference exposure.
 * @visibility root
 */
final class PlanFootprints
{
    /**
     * @param PlanValidation $validation Shared finite-plan validation
     */
    public function __construct(public readonly PlanValidation $validation)
    {
    }

    /**
     * Checks action metadata and reference-return compatibility.
     * @param Action $action Ordered plan effect
     */
    public function action(Action $action): void
    {
        $v = $this->validation;
        $count = ['location-write' => 1, 'alias' => 2, 'return-reference' => 1, 'havoc' => count($action->locations)][$action->operation] ?? 0;
        if (count($action->locations) !== $count || ($action->arguments !== [] && !in_array($action->operation, ['invoke', 'allocate'], true))) {
            $v->errors[] = 'action-location-or-arguments:' . $action->operation;
        }
        $this->completion($action);
        if ($action->operation !== 'choice' && ($action->yes !== [] || $action->no !== [])) {
            $v->errors[] = 'unexpected-action-branches';
        }
        foreach ($action->locations as $location) {
            $this->location($location, true);
        }
        foreach ($action->arguments as $argument) {
            if ($argument->value instanceof LocationRef) {
                $this->location($argument->value, true);
            } else {
                $v->expression($argument->value);
            }
            if ($argument->unpack && $argument->name !== null) {
                $v->errors[] = 'named-unpack';
            }
        }
    }

    /**
     * Checks that declared completion metadata agrees with the signature and action.
     * @param Action $action Ordered effect or return
     */
    public function completion(Action $action): void
    {
        $v = $this->validation;
        if ($action->mayThrow && $action->operation !== 'havoc') {
            $v->errors[] = 'invalid-havoc-completion';
        }
        if ($action->referenceResult && $action->operation !== 'invoke') {
            $v->errors[] = 'invalid-reference-result';
        }
        if ($action->operation === 'return' && $v->signature?->byReference === true) {
            $v->errors[] = 'reference-return-requires-location';
        }
        if ($action->operation === 'return-reference' && $v->signature !== null && !$v->signature->byReference) {
            $v->errors[] = 'undeclared-reference-return';
        }
    }

    /**
     * Checks a nested location and records effects on its root storage.
     * @param LocationRef $location Reference descriptor
     * @param bool $write Whether storage can be changed or exposed by reference
     */
    public function location(LocationRef $location, bool $write): void
    {
        $v = $this->validation;
        if (++$v->nodes > 20000) {
            $v->errors[] = 'plan-node-budget';
            return;
        }
        if (!$this->shape($location)) {
            $v->errors[] = 'invalid-location:' . $location->kind;
            return;
        }
        if ($location->kind === 'parameter') {
            if ($write && $this->external($location->name)) {
                $v->writes[] = 'parameter:' . $location->name;
            }
            return;
        }
        if ($location->kind === 'state' && $location->receiver !== null) {
            $v->expression($location->receiver);
            if ($write) {
                $v->writes[] = $location->name;
            } else {
                $v->reads[] = $location->name;
            }
            return;
        }
        if ($location->kind === 'element' && $location->parent !== null) {
            $this->location($location->parent, false);
            if ($write) {
                $this->location($location->parent, true);
            }
            if ($location->key !== null) {
                $v->expression($location->key);
            }
            return;
        }
        $v->errors[] = 'invalid-location:' . $location->kind;
    }

    /**
     * Rejects contradictory descriptor fields before traversing a location.
     * @param LocationRef $location Declarative address
     * @return bool Whether required and forbidden fields match its kind
     */
    public function shape(LocationRef $location): bool
    {
        return match ($location->kind) {
            'parameter' => $location->name !== '' && $location->receiver === null && $location->parent === null && $location->key === null,
            'state' => $location->name !== '' && $location->receiver !== null && $location->parent === null && $location->key === null,
            'element' => $location->name === '' && $location->receiver === null && $location->parent !== null,
            default => false,
        };
    }

    /**
     * Distinguishes a caller-owned reference parameter from a model-local result.
     * @param string $name Binding name
     * @return bool Whether a direct write can change caller storage
     */
    public function external(string $name): bool
    {
        if ($this->validation->signature === null) {
            return !str_starts_with($name, '@');
        }
        foreach ($this->validation->signature->parameters as $parameter) {
            if ($parameter->name === $name) {
                return $parameter->byReference;
            }
        }
        return false;
    }
}
