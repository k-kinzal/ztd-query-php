<?php

declare(strict_types=1);

namespace Deriver\Analysis;

use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Program;
use Deriver\Exception\InvalidInputException;
use Deriver\Query\Query;
use Deriver\Query\ReturnQuery;
use Deriver\Query\StateQuery;
use Deriver\Query\TupleQuery;
use Deriver\Query\ValueQuery;
use Deriver\Reference\ExpressionRef;
use Deriver\Reference\PointRef;

/**
 * Rejects foreign, fabricated, and incompatible observation references.
 * @visibility root
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
        if ($reference instanceof PointRef && !in_array($reference->phase, ['before', 'invocation', 'after'], true)) {
            throw new InvalidInputException('Unknown observation phase.');
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
