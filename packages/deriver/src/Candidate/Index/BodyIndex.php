<?php

declare(strict_types=1);

namespace Deriver\Candidate\Index;

use Deriver\ControlFlow\CallableGraph;
use Deriver\ControlFlow\Instruction;

/**
 * Indexes definitions and predecessor edges without evaluating instructions.
 * @visibility parent
 */
final class BodyIndex
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

    /**
     * Identifies operations that update a storage address.
     */
    public static function writes(Instruction $instruction): bool
    {
        return in_array($instruction->operation, ['write', 'compound', 'increment', 'unset'], true);
    }

    /**
     * Finds the root address a write updates, including element updates inside a property.
     */
    public function root(Instruction $write): ?Instruction
    {
        $address = $this->definitions[$write->operands[0] ?? ''] ?? null;
        while ($address?->operation === 'element-address') {
            $address = $this->definitions[$address->operands[0]] ?? null;
        }
        return $address;
    }
}
