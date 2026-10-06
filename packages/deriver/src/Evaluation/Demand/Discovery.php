<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Demand;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Context;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;

/**
 * Discovers backward SSA dependencies while retaining all possible state and exit effects.
 * @visibility root
 */
final class Discovery
{
    /**
     * @param Context $context Query roots and logical graph budget
     */
    public function __construct(public readonly Context $context)
    {
    }

    /**
     * Closes demanded definitions with a deterministic finite worklist.
     * @param CallableGraph $callable Graph being evaluated
     * @return array<string, true> Demanded instruction identifiers
     */
    public function instructions(CallableGraph $callable): array
    {
        $definitions = [];
        $pending = [];
        foreach ($callable->blocks as $block) {
            $pending[] = $block->terminator->operand;
            foreach ($block->instructions as $instruction) {
                $definitions[$instruction->result] = $instruction;
                if ($this->root($callable, $instruction)) {
                    $pending[] = $instruction->result;
                }
            }
        }
        $needed = [];
        while ($pending !== []) {
            $register = array_pop($pending);
            $instruction = $definitions[$register] ?? null;
            if ($instruction === null || isset($needed[$instruction->id])) {
                continue;
            }
            $needed[$instruction->id] = true;
            array_push($pending, ...$instruction->operands);
            foreach ($instruction->arguments as $argument) {
                $pending[] = $argument->register;
                $pending[] = $argument->location ?? '';
            }
            if (count($needed) > $this->context->query->budget()->nodes) {
                $this->context->sealed = true;
                $this->context->frontier('BUDGET_EXCEEDED', $instruction->source, 'dependency-discovery');
                break;
            }
        }
        return $needed;
    }

    /**
     * Seeds observations, effects, and instructions that can warn or throw.
     * @param CallableGraph $callable Owning graph
     * @param Instruction $instruction Candidate definition
     * @return bool Whether the definition must be evaluated independently of its result
     */
    public function root(CallableGraph $callable, Instruction $instruction): bool
    {
        if (!in_array($instruction->operation, ['constant', 'copy', 'phi', 'not-null', 'magic-constant'], true)) {
            return true;
        }
        if ($this->context->batch !== null) {
            $demand = $this->context->batch->demands[(new CallableIdentity())->key($callable->symbol)] ?? [];
            return isset($demand['register:' . $instruction->result]) || isset($demand['instruction:' . $instruction->id]);
        }
        return $this->observation($this->context->query, $callable, $instruction);
    }

    /**
     * Selects the pure definitions explicitly requested by one observation.
     * @param \Deriver\Query\Query $query Requested observation
     * @param CallableGraph $callable Owning graph
     * @param Instruction $instruction Candidate definition
     * @return bool Whether this query demands the definition
     */
    public function observation(\Deriver\Query\Query $query, CallableGraph $callable, Instruction $instruction): bool
    {
        if ($query instanceof ReturnQuery) {
            return false;
        }
        if ($query instanceof ValueQuery) {
            return (new CallableIdentity())->key($query->expression->callable) === (new CallableIdentity())->key($callable->symbol) && $query->expression->register === $instruction->result;
        }
        if ($query instanceof StateQuery || $query instanceof TupleQuery) {
            if ((new CallableIdentity())->key($query->point->callable) !== (new CallableIdentity())->key($callable->symbol)) {
                return false;
            }
            if ($query->point->instruction === $instruction->id) {
                return true;
            }
            if ($query instanceof TupleQuery) {
                foreach ($query->values as $reference) {
                    if ($reference->register === $instruction->result) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}
