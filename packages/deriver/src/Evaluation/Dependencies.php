<?php

declare(strict_types=1);

namespace Deriver\Evaluation;

use Deriver\ControlFlow\Instruction;
use Deriver\Result\Derivation;

/**
 * Records concrete definition, storage, call, and control dependencies as graph IDs.
 * @visibility root
 */
final class Dependencies
{
    /**
     * @param Context $context Query-local explanation graph
     */
    public function __construct(public readonly Context $context)
    {
    }
    /**
     * Captures an instruction's evaluated input definitions and relevant storage writes.
     * @param Instruction $instruction Evaluated instruction
     * @param State $before Input path
     * @param list<State> $paths Output paths
     */
    public function record(Instruction $instruction, State $before, array $paths): void
    {
        $parents = $before->controls;
        $registers = $instruction->operands;
        foreach ($instruction->arguments as $argument) {
            $registers[] = $argument->register;
        }
        foreach ($registers as $register) {
            if (isset($before->producers[$register])) {
                $parents[] = $before->producers[$register];
            }
            $location = $before->addresses[$register] ?? null;
            if ($location !== null && isset($before->memory->writers[$location->root])) {
                $parents[] = $before->memory->writers[$location->root];
            }
        }
        foreach ($paths as $path) {
            array_push($parents, ...array_diff($path->evidence, $before->evidence));
        }
        $previous = $this->context->evidence[$instruction->id] ?? null;
        $parents = array_values(array_unique([...$parents, ...($previous->parents ?? [])]));
        $parents = array_values(array_filter($parents, static fn (string $parent): bool => $parent !== $instruction->id));
        $effect = in_array($instruction->operation, ['write', 'alias', 'increment', 'unset', 'global', 'static-local'], true);
        $call = in_array($instruction->operation, ['invoke', 'invoke-method', 'invoke-static', 'new', 'clone', 'intrinsic'], true);
        $kind = $effect ? 'effect' : ($call ? 'call' : 'data');
        $this->context->evidence[$instruction->id] = new Derivation($instruction->id, $kind, $instruction->source, $parents, $instruction->operation);
        foreach ($paths as $path) {
            $path->producers[$instruction->result] = $instruction->id;
            $location = $path->addresses[$instruction->operands[0] ?? ''] ?? null;
            if ($effect && $location !== null) {
                $path->memory->writers[$location->root] = $instruction->id;
            }
        }
    }
}
