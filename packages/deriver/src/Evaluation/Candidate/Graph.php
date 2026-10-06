<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;

/**
 * Indexes definitions and predecessor edges without evaluating instructions.
 * @visibility root
 */
final class Graph
{
    /**
     * @var array<string, Instruction>
     */
    public array $definitions = [];
    /**
     * @var array<string, array{int, int}>
     */
    public array $positions = [];
    /**
     * @var array<int, list<int>>
     */
    public array $predecessors = [];
    /**
     * @var array<string, string>
     */
    public array $instructions = [];
    /**
     * @var array<int, list<int>> Normalized value continuations
     */
    public array $successors = [];
    /**
     * @var array<int, list<array{int, int}>>|null
     */
    public ?array $controls = null;
    /**
     * Whether reaching writes require alias-map construction.
     */
    public bool $hasAliases = false;

    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly CallableGraph $body, public readonly ?\Deriver\Result\Evidence\Node $modelEvidence = null)
    {
        foreach ($body->blocks as $block) {
            foreach ($block->instructions as $offset => $instruction) {
                $this->definitions[$instruction->result] = $instruction;
                $this->positions[$instruction->result] = [$block->id, $offset];
                $this->instructions[$instruction->id] = $instruction->result;
                $this->hasAliases = $this->hasAliases || in_array($instruction->operation, ['alias', 'unset'], true);
            }
            foreach ($block->terminator->targets as $target) {
                $this->predecessors[$target][] = $block->id;
            }
            $this->successors[$block->id] = $block->terminator->targets;
        }
        foreach ($body->blocks as $block) {
            foreach (array_reverse($body->regions) as $region) {
                if ($region->finally !== null && in_array($block->terminator->kind, ['return', 'throw'], true) && in_array($block->id, [...$region->protectedBlocks, ...$region->catchBlocks], true)) {
                    $this->predecessors[$region->finally][] = $block->id;
                }
                $target = null;
                if ($block->terminator->kind === 'leave-try' && in_array($block->id, [...$region->protectedBlocks, ...$region->catchBlocks], true)) {
                    $target = $region->finally ?? $region->continuation;
                } elseif ($block->terminator->kind === 'resume' && in_array($block->id, $region->finallyBlocks, true)) {
                    $target = $region->continuation;
                }
                if ($target !== null) {
                    $this->successors[$block->id] = [$target];
                    $this->predecessors[$target][] = $block->id;
                    break;
                }
            }
        }
    }

    /**

     * @return list<array{int, string}>

     */
    public function returns(int $start = 0): array
    {
        $returns = [];
        $pending = [$start];
        $seen = [];
        while ($pending !== []) {
            $id = array_shift($pending);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $block = $this->body->blocks[$id];
            array_push($pending, ...($this->successors[$id] ?? []));
            if (in_array($block->terminator->kind, ['return', 'throw'], true)) {
                $returns[] = [$block->id, $block->terminator->operand];
            }
        }
        return $returns;
    }

}
