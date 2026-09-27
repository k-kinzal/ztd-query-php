<?php

declare(strict_types=1);

namespace Deriver\Analysis;

use Deriver\ControlFlow\CallableIdentity;
use Deriver\ControlFlow\Program;
use Deriver\Reference\ExpressionRef;
use Deriver\Reference\Observation;

/**
 * Projects source call instructions into stable public observation references.
 * @visibility root
 */
final class CallObservations
{
    /**
     * @param Program $program Captured callable graphs
     */
    public function __construct(public readonly Program $program)
    {
    }

    /**
     * Finds statically named invocations in the captured world.
     * @param string $symbol Function or method selector
     * @return list<Observation> Matching call sites
     */
    public function find(string $symbol): array
    {
        $result = [];
        foreach ($this->program->callOwners($symbol) as $owner) {
            $callable = $this->program->callable($owner);
            if ($callable === null) {
                continue;
            }
            $constants = [];
            $sources = [];
            foreach ($callable->blocks as $block) {
                foreach ($block->instructions as $instruction) {
                    $sources[$instruction->result] = $instruction->source;
                    if ($instruction->constant !== null) {
                        $constants[$instruction->result] = $instruction->constant;
                    }
                    if (!in_array($instruction->operation, ['invoke', 'invoke-method', 'invoke-static'], true)) {
                        continue;
                    }
                    $index = $instruction->operation === 'invoke' ? 0 : 1;
                    $name = $constants[$instruction->operands[$index] ?? '']->literal ?? null;
                    if (!is_string($name) || (new CallableIdentity())->key($name) !== (new CallableIdentity())->key($symbol)) {
                        continue;
                    }
                    $arguments = [];
                    foreach ($instruction->arguments as $position => $argument) {
                        $reference = new ExpressionRef($sources[$argument->register] ?? $instruction->source, $owner, $argument->register);
                        $arguments[$position] = $reference;
                        if ($argument->name !== null) {
                            $arguments[$argument->name] = $reference;
                        }
                    }
                    $result[] = new Observation($instruction->source, $owner, $instruction->id, $name, $arguments, new ExpressionRef($instruction->source, $owner, $instruction->result));
                }
            }
        }
        usort($result, static fn (Observation $a, Observation $b): int => [$a->source->path, $a->source->start, $a->callable] <=> [$b->source->path, $b->source->start, $b->callable]);
        return $result;
    }
}
