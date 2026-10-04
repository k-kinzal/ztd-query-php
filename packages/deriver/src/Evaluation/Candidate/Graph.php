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
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly CallableGraph $body)
    {
        foreach ($body->blocks as $block) {
            foreach ($block->instructions as $offset => $instruction) {
                $this->definitions[$instruction->result] = $instruction;
                $this->positions[$instruction->result] = [$block->id, $offset];
                $this->instructions[$instruction->id] = $instruction->result;
            }
            foreach ($block->terminator->targets as $target) {
                $this->predecessors[$target][] = $block->id;
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
            array_push($pending, ...$block->terminator->targets);
            if (in_array($block->terminator->kind, ['return', 'throw'], true)) {
                $returns[] = [$block->id, $block->terminator->operand];
            }
        }
        return $returns;
    }
}
