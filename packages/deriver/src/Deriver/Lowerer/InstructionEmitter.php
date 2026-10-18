<?php

declare(strict_types=1);

namespace Deriver\Deriver\Lowerer;

use Deriver\Deriver\Lowerer\Graph\Argument;
use Deriver\Deriver\Lowerer\Graph\BasicBlock;
use Deriver\Deriver\Lowerer\Graph\ExceptionRegion;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Lowerer\Graph\Literal;
use Deriver\Deriver\Lowerer\Graph\Terminator;
use Deriver\Deriver\Sources\SourceRef;
use PhpParser\Node;

/**
 * Builds explicit blocks and SSA temporaries for one callable.
 * @visibility Deriver\Deriver\Lowerer\Declaration
 * @visibility Deriver\Deriver\Lowerer
 * @visibility Deriver\Deriver\Sources
 */
final class InstructionEmitter
{
    /**
     * Source coordinates shared across every callable lowered from the file.
     */
    public readonly \Deriver\Deriver\Sources\LineMap $lines;

    /**
     * @var array<int, list<Instruction>> Instructions by block.
     */
    public array $instructions = [0 => []];
    /**
     * @var array<int, Terminator> Completed control transfers.
     */
    public array $terminators = [];
    /**
     * @var array<int, bool> Loop headers.
     */
    public array $headers = [];
    /**
     * @var array<int, ExceptionRegion> Exception regions.
     */
    public array $regions = [];
    /**
     * Current block.
     */
    public int $current = 0;
    /**
     * Next temporary identifier.
     */
    public int $sequence = 0;
    /**
     * Active lexical exception depth.
     */
    public int $handlerDepth = 0;
    /**
     * @var list<array{break: int, continue: int, depth: int}> Active loops.
     */
    public array $loops = [];
    /**
     * @var list<array{key: string, kind: Control\ScopeKind, iterator: string, depth: int}> Enclosing loop, switch, and exception scopes, outermost first.
     */
    public array $scopes = [];
    /**
     * @var array<string, array{block: int, scopes: list<array{key: string, kind: Control\ScopeKind, iterator: string, depth: int}>, depth: int, position: int}|null> Goto labels, or null when declared twice.
     */
    public array $labels = [];
    /**
     * @var list<array{block: int, node: Node\Stmt\Goto_, scopes: list<array{key: string, kind: Control\ScopeKind, iterator: string, depth: int}>}> Goto statements awaiting label resolution.
     */
    public array $gotos = [];

    /**
     * @param string $snapshot Snapshot identifier
     * @param string $path Source path
     * @param string $contents Captured bytes
     * @param \Deriver\Deriver\Sources\LineMap|null $lines Precomputed source positions
     */
    public function __construct(public readonly string $snapshot, public readonly string $path, public readonly string $contents, ?\Deriver\Deriver\Sources\LineMap $lines = null)
    {
        $this->lines = $lines ?? new \Deriver\Deriver\Sources\LineMap($contents);
    }

    /**
     * Allocates a new basic block.
     * @param bool $header Whether it is a loop header
     * @return int Block identifier
     */
    public function block(bool $header = false): int
    {
        $id = count($this->instructions);
        $this->instructions[$id] = [];
        $this->headers[$id] = $header;
        return $id;
    }

    /**
     * Emits a single SSA definition.
     * @param Node $node Original expression or statement
     * @param string $operation Operation name
     * @param list<string> $operands Register inputs
     * @param string $name Symbol, field, or operator
     * @param Literal|null $constant Literal payload
     * @param list<Argument> $arguments Normalized argument syntax
     * @param array<string, scalar|null> $attributes Other instruction parameters
     * @return string Defined register
     */
    public function emit(Node $node, string $operation, array $operands = [], string $name = '', ?Literal $constant = null, array $arguments = [], array $attributes = []): string
    {
        $register = 'r' . $this->sequence++;
        $source = $this->source($node);
        $id = $this->path . ':' . $node->getStartFilePos() . ':' . $register;
        $this->instructions[$this->current][] = new Instruction($id, $operation, $source, $register, $operands, $name, $constant, $arguments, $attributes);
        return $register;
    }

    /**
     * Converts parser byte positions to a public source reference.
     * @param Node $node Source node
     * @return SourceRef Half-open source range
     */
    public function source(Node $node): SourceRef
    {
        $start = max(0, $node->getStartFilePos());
        $end = max($start, $node->getEndFilePos() + 1);
        return new SourceRef($this->snapshot, $this->path, $start, $end, max(1, $node->getStartLine()), $this->lines->column($start), $this->lines->line($end), $this->lines->column($end));
    }

    /**
     * Ends the current block.
     * @param Terminator $terminator Control transfer
     */
    public function end(Terminator $terminator): void
    {
        $this->terminators[$this->current] = $terminator;
    }

    /**
     * Adds an ordinary edge unless the block already completes.
     * @param int $target Successor
     */
    public function jump(int $target): void
    {
        if (!isset($this->terminators[$this->current])) {
            $this->end(new Terminator(Graph\TerminatorKind::Jump, targets: [$target]));
        }
    }

    /**
     * Freezes the graph after lowering.
     * @return array<int, BasicBlock> Blocks with explicit fall-through return
     */
    public function finish(): array
    {
        $blocks = [];
        foreach ($this->instructions as $id => $instructions) {
            $blocks[$id] = new BasicBlock($id, $instructions, $this->terminators[$id] ?? new Terminator(Graph\TerminatorKind::Return), $this->headers[$id] ?? false);
        }
        return $blocks;
    }
}
