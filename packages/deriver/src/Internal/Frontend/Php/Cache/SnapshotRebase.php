<?php

declare(strict_types=1);

namespace Deriver\Internal\Frontend\Php\Cache;

use Deriver\Api\Reference\SourceRef;
use Deriver\Internal\IR\BasicBlock;
use Deriver\Internal\IR\CallableIR;
use Deriver\Internal\IR\Instruction;
use Deriver\Internal\IR\Parameter;

/**
 * Rebinds source-owned immutable IR references when unchanged files enter a new snapshot.
 * @visibility root
 */
final class SnapshotRebase
{
    /**
     * @param string $snapshot New manifest identity
     */
    public function __construct(public readonly string $snapshot)
    {
    }

    /**
     * Copies source ownership while keeping register, control-flow, and lexical identities.
     * @param CallableIR $body Source-only graph
     * @return CallableIR Equivalent graph owned by the new immutable world
     */
    public function callable(CallableIR $body): CallableIR
    {
        if ($body->source->snapshotId === $this->snapshot) {
            return $body;
        }
        $parameters = [];
        foreach ($body->parameters as $parameter) {
            $parameters[] = new Parameter($parameter->name, $parameter->type, $parameter->byReference, $parameter->variadic, $parameter->default === null ? null : $this->callable($parameter->default), $parameter->promotion);
        }
        $blocks = [];
        foreach ($body->blocks as $id => $block) {
            $blocks[$id] = new BasicBlock($block->id, array_map($this->instruction(...), $block->instructions), $block->terminator, $block->loopHeader);
        }
        return new CallableIR($body->symbol, $parameters, $blocks, $this->source($body->source), $body->returnType, $body->byReference, $body->strict, $body->className, $body->captures, $body->regions, $body->allowExtraArguments, $body->visibility, $body->static, $body->abstract, $body->external);
    }

    /**
     * Copies an instruction's source ownership without altering its ordered operands.
     * @param Instruction $instruction Source instruction
     * @return Instruction Equivalent source-rebased definition
     */
    public function instruction(Instruction $instruction): Instruction
    {
        return new Instruction($instruction->id, $instruction->operation, $this->source($instruction->source), $instruction->result, $instruction->operands, $instruction->name, $instruction->constant, $instruction->arguments, $instruction->attributes);
    }

    /**
     * Preserves byte ranges and display coordinates in the receiving snapshot.
     * @param SourceRef $source Previous source reference
     * @return SourceRef Current snapshot reference
     */
    public function source(SourceRef $source): SourceRef
    {
        return new SourceRef($this->snapshot, $source->path, $source->start, $source->end, $source->line, $source->column);
    }
}
