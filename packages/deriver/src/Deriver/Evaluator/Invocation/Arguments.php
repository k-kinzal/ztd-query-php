<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Invocation;

use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Scope\Binding;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;

/**
 * Normalizes argument syntax once; ordinary actuals remain lazy bindings.
 * @visibility parent
 */
final class Arguments
{
    /**
     * Uses the common dependency engine to resolve demanded unpacked inputs.
     */
    public function __construct(private readonly Evaluator $engine)
    {
    }

    /**

     * @return array<int|string, Binding|Term>

     */
    public function actuals(Frame $caller, Instruction $call, int $depth): array
    {
        $actuals = [];
        foreach ($call->arguments as $argument) {
            if (!$argument->unpack) {
                $binding = new Binding($caller, $argument->register);
                if ($argument->name !== null) {
                    $actuals[$argument->name] = $binding;
                } else {
                    $actuals[] = $binding;
                }
                continue;
            }
            $array = $this->engine->value($caller, $argument->register, $depth);
            if ($array->kind !== \Deriver\Deriver\Evaluator\TermKind::Array || ($array->attributes['open'] ?? false) === true) {
                $actuals['*'] = new Term(\Deriver\Deriver\Evaluator\TermKind::ArgumentUnpack, operands: [$array], attributes: ['reason' => 'UNRESOLVED_UNPACK']);
                continue;
            }
            foreach ($array->operands as $key => $value) {
                if (is_int($key)) {
                    $actuals[] = $value;
                } else {
                    $actuals[$key] = $value;
                }
            }
        }
        return $actuals;
    }

    /**
     * @param list<\Deriver\Deriver\Lowerer\Graph\Parameter> $parameters
     * @return array<string, Binding|Term>
     */
    public function bind(Frame $caller, Instruction $call, array $parameters, int $depth): array
    {
        $actuals = $this->actuals($caller, $call, $depth);
        $bindings = [];
        foreach ($parameters as $position => $parameter) {
            if ($parameter->variadic) {
                $entries = [];
                foreach ($actuals as $key => $value) {
                    $entries[$key] = $value instanceof Binding ? $value->value($this->engine, $parameter->type, $depth) : $value;
                }
                $bindings[$parameter->name] = Term::array($entries, isset($actuals['*']));
                break;
            }
            $key = array_key_exists($parameter->name, $actuals) ? $parameter->name : $position;
            if (isset($actuals[$key])) {
                $bindings[$parameter->name] = $actuals[$key];
                unset($actuals[$key]);
            } elseif (isset($actuals['*'])) {
                $bindings[$parameter->name] = $actuals['*'];
            }
        }
        return $bindings;
    }
}
