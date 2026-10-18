<?php

declare(strict_types=1);

namespace Deriver\Deriver\Selection;

use Deriver\Deriver\Evaluator\Request\Query;
use Deriver\Deriver\Evaluator\Request\ReturnQuery;
use Deriver\Deriver\Evaluator\Request\StateQuery;
use Deriver\Deriver\Evaluator\Request\TupleQuery;
use Deriver\Deriver\Evaluator\Request\ValueQuery;
use Deriver\Deriver\Lowerer\Declaration\CallableIdentity;
use Deriver\Deriver\Lowerer\Graph\ExpressionRef;
use Deriver\Deriver\Lowerer\Graph\PointRef;
use Deriver\Deriver\Lowerer\Graph\Program;
use Deriver\Exception\InvalidInputException;

/**
 * Rejects foreign, fabricated, and incompatible observation references.
 * @visibility parent
 * @visibility Deriver\Deriver\Execution
 */
final class QueryValidation
{
    /**
     * @param Program $program Captured graph index
     * @param string $snapshotId Snapshot identity
     */
    public function __construct(public readonly Program $program, public readonly string $snapshotId)
    {
    }

    /**
     * Validates all query references and returns their callable owner.
     * @param Query $query Observation contract
     * @return string Owning callable
     * @throws InvalidInputException If any reference is invalid
     */
    public function owner(Query $query): string
    {
        if ($query instanceof \Deriver\Deriver\Evaluator\Request\ParameterQuery) {
            foreach ($this->program->callable($query->symbol)->parameters ?? [] as $parameter) {
                if ($parameter->name === $query->parameter) {
                    return $query->symbol;
                }
            }
            throw new InvalidInputException('The target is not a captured formal parameter.');
        }
        if ($query instanceof ReturnQuery) {
            if ($query->symbol === '') {
                throw new InvalidInputException('Return queries require a callable symbol.');
            }
            return $query->symbol;
        }
        $reference = match (true) {
            $query instanceof ValueQuery => $query->expression,
            $query instanceof StateQuery, $query instanceof TupleQuery => $query->point,
            default => throw new InvalidInputException('Unsupported query implementation.'),
        };
        $this->reference($reference);
        if ($query instanceof TupleQuery) {
            if ($query->values === []) {
                throw new InvalidInputException('Tuple queries require at least one expression.');
            }
            foreach ($query->values as $expression) {
                $this->reference($expression);
                if ((new CallableIdentity())->key($expression->callable) !== (new CallableIdentity())->key($reference->callable)) {
                    throw new InvalidInputException('Tuple expressions must belong to the observation callable.');
                }
            }
        }
        return $reference->callable;
    }

    /**
     * Checks an expression or point against a captured instruction.
     * @param ExpressionRef|PointRef $reference Public source reference
     * @throws InvalidInputException If the reference cannot identify the requested graph position
     */
    public function reference(ExpressionRef|PointRef $reference): void
    {
        if ($reference->source->snapshotId !== $this->snapshotId) {
            throw new InvalidInputException('Source reference belongs to a different snapshot.');
        }
        $body = $this->program->callable($reference->callable);
        foreach ($body->blocks ?? [] as $block) {
            foreach ($block->instructions as $instruction) {
                $matches = $reference instanceof PointRef ? $reference->instruction === $instruction->id : $reference->register === $instruction->result;
                if ($matches && $reference->source->path === $instruction->source->path && $reference->source->start === $instruction->source->start && $reference->source->end === $instruction->source->end) {
                    return;
                }
            }
        }
        throw new InvalidInputException('Observation reference does not identify an instruction in this snapshot.');
    }
}
