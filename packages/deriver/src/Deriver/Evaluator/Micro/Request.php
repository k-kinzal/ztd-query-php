<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Micro;

use Deriver\Deriver\Evaluator\Evaluator;
use Deriver\Deriver\Evaluator\Scope\Frame;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Exception\EvaluationContractException;

/**
 * Internal operation context with dependencies evaluated by the shared search.
 * @visibility root
 */
final class Request
{
    /**
     * @var array<int|string, Term> Inputs actually requested by this evaluator
     */
    private array $inputs = [];

    /**
     * Captures one evaluation site without evaluating its children.
     * @throws EvaluationContractException If no operation site is supplied
     */
    public function __construct(public readonly Evaluator $engine, public readonly Frame $frame, public readonly ?Instruction $instruction, public readonly int $depth, public readonly ?string $function = null, public readonly ?Site $site = null)
    {
        if ($site === null && $instruction === null) {
            throw new EvaluationContractException('An evaluation request requires an operation site.');
        }

    }

    /**
     * Returns the resolved function name or exact operation name.
     */
    public function name(): string
    {
        return $this->site->name ?? ($this->function === null ? $this->instruction()->name : strtolower(ltrim($this->function, '\\')));
    }

    /**
     * Identifies a registered operation without exposing its graph representation.
     */
    public function operation(): string
    {
        return $this->site->operation ?? ($this->function === null ? $this->instruction()->operation : 'function');
    }

    /**
     * Locates the selected operation or declaration in captured source.
     */
    public function source(): \Deriver\Deriver\Sources\SourceRef
    {
        return $this->site->source ?? $this->instruction()->source;
    }

    /**
     * Evaluates only the requested operand or direct argument.
     * @throws EvaluationContractException If the evaluator requests an absent input
     */
    public function input(int|string $key): Term
    {
        return $this->inputs[$key] ??= $this->site === null ? $this->engine->value($this->frame, $this->register($key), $this->depth) : ($this->site->input)($key);
    }

    /**
     * Demands only boolean possibilities, separately from complete value inputs.
     */
    public function truth(int|string $key): Term
    {
        return $this->inputs['truth:' . $key] ??= $this->site === null ? (new \Deriver\Deriver\Evaluator\Predicate\Truth($this->engine))->value($this->frame, $this->register($key), $this->depth) : ($this->site->truth)($key);
    }

    /**
     * Selects an instruction input without demanding it.
     * @throws EvaluationContractException If the input is absent
     */
    public function register(int|string $key): string
    {
        $instruction = $this->instruction();
        $register = $this->function === null ? ($instruction->operands[$key] ?? null) : null;
        if ($this->function !== null) {
            foreach ($instruction->arguments as $position => $argument) {
                if ($position === $key || $argument->name === $key) {
                    $register = $argument->register;
                    break;
                }
            }
        }
        if ($register === null) {
            throw new EvaluationContractException('The micro evaluator requested an absent input: ' . $key);
        }
        return $register;
    }

    /**
     * Supplies an instruction only to built-ins that evaluate instructions.
     * @throws EvaluationContractException If the built-in receives another kind of site
     */
    public function instruction(): Instruction
    {
        return $this->instruction ?? throw new EvaluationContractException('This evaluator requires an instruction site.');
    }

    /**
     * @return array<int|string, Term> Evaluated dependencies for evidence
     */
    public function inputs(): array
    {
        return $this->inputs;
    }
}
