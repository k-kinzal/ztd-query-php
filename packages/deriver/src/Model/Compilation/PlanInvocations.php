<?php

declare(strict_types=1);

namespace Deriver\Model\Compilation;

use Deriver\ControlFlow\Argument;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\CallArgument;
use Deriver\Model\Plan\Expression;

/**
 * Routes model callbacks and allocations through source call preparation and argument binding.
 * @visibility root
 */
final class PlanInvocations
{
    /**
     * @param PlanCompiler $compiler Shared instruction builder
     */
    public function __construct(public readonly PlanCompiler $compiler)
    {
    }

    /**
     * Preserves target resolution, argument evaluation, and result reference ordering.
     * @param Action $action Callback, invocation, or allocation
     */
    public function apply(Action $action): void
    {
        $c = $this->compiler;
        $target = $c->expression($action->operands[0]);
        $operation = $action->operation === 'allocate' ? 'new' : 'invoke';
        $prepared = $c->emit('call-prepare', [$target], attributes: ['call-operation' => $operation]);
        $arguments = $action->operation === 'callback' ? array_map(static fn (Expression $value): CallArgument => new CallArgument($value), array_slice($action->operands, 1)) : $action->arguments;
        $result = $c->emit($operation, [$target], arguments: $this->arguments($arguments, $prepared), attributes: ['prepared' => $prepared]);
        $destination = $c->emit('local', name: $action->name);
        if ($action->referenceResult) {
            $c->emit('alias', [$destination, $c->emit('returned-address', [$result])]);
            return;
        }
        $c->emit('write', [$destination, $result]);
    }

    /**
     * Uses declared callee modes for positional, named, unpacked, and reference arguments.
     * @param list<CallArgument> $arguments Ordered model arguments
     * @param string $prepared Selected call signature
     * @return list<Argument> Shared IR arguments
     */
    public function arguments(array $arguments, string $prepared): array
    {
        $result = [];
        $locations = new PlanLocations($this->compiler);
        foreach ($arguments as $argument) {
            $location = $locations->fromExpression($argument->value);
            $value = $location === null && $argument->value instanceof Expression ? $this->compiler->expression($argument->value) : $locations->address($location ?? $argument->value);
            $register = $this->compiler->emit('argument', [$prepared, $value], arguments: $result, attributes: ['address' => $location !== null, 'argument-name' => $argument->name, 'unpack' => $argument->unpack, 'unpack-variable' => $location?->kind === 'parameter', 'temporary' => false]);
            $result[] = new Argument($register, $argument->name, $argument->unpack, $register);
        }
        return $result;
    }
}
