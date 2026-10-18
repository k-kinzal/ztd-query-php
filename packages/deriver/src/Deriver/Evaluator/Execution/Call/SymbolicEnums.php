<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Call;

use Deriver\Deriver\Evaluator\Execution\Evaluator;
use Deriver\Deriver\Evaluator\Execution\State;
use Deriver\Deriver\Evaluator\Execution\Transfer\ConstantTransfer;
use Deriver\Deriver\Lowerer\Graph\CallableGraph;
use Deriver\Deriver\Lowerer\Graph\Parameter;

/**
 * Enumerates the closed set of declared enum cases for a symbolic parameter.
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class SymbolicEnums
{
    /**
     * @param Evaluator $machine Shared evaluator and limits
     */
    public function __construct(public readonly Evaluator $machine)
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
            $instruction = new \Deriver\Deriver\Evaluator\Call\BoundInstruction($callable->symbol . ':parameter:' . $parameter->name, 'constant-fetch', $callable->source, 'enum-value');
            foreach ((new ConstantTransfer($this->machine))->initializer($class->name . '::' . $constant->name, $instruction, $state->fork(), $constant) as $path) {
                $path->memory->write($path->local($parameter->name), $path->value('enum-value'));
                $result[] = $path;
            }
        }
        return $result;
    }
}
