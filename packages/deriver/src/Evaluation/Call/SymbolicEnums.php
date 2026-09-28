<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Call;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;
use Deriver\ControlFlow\Parameter;
use Deriver\Evaluation\Machine;
use Deriver\Evaluation\State;
use Deriver\Evaluation\Transfer\ConstantTransfer;

/**
 * Enumerates the closed set of declared enum cases for a symbolic parameter.
 * @visibility root
 */
final class SymbolicEnums
{
    /**
     * @param Machine $machine Shared evaluator and limits
     */
    public function __construct(public readonly Machine $machine)
    {
    }

    /**
     * @param CallableGraph $callable Owning signature
     * @param Parameter $parameter Symbolic formal
     * @param State $state Partially bound frame
     * @param string $type Resolved declaration type
     * @return list<State>|null Case bindings, or no finite enum expansion
     */
    public function bind(CallableGraph $callable, Parameter $parameter, State $state, string $type): ?array
    {
        $class = $this->machine->context->program->classes()[strtolower($type)] ?? null;
        if ($class?->enum !== true || $parameter->variadic || count($class->constantDeclarations) > $this->machine->context->query->budget()->partitions) {
            return null;
        }
        $result = [];
        foreach ($class->constantDeclarations as $constant) {
            if (!$constant->enum) {
                continue;
            }
            $instruction = new Instruction($callable->symbol . ':parameter:' . $parameter->name, 'constant-fetch', $callable->source, 'enum-value');
            foreach ((new ConstantTransfer($this->machine))->initializer($class->name . '::' . $constant->name, $instruction, $state->fork(), $constant) as $path) {
                $path->memory->write($path->local($parameter->name), $path->value('enum-value'));
                $result[] = $path;
            }
        }
        return $result;
    }
}
