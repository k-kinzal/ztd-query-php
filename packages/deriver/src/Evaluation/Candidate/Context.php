<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate;

use Deriver\ControlFlow\Instruction;
use Deriver\Evaluation\Control\Resources;
use Deriver\Model\Registration\Registry;
use Deriver\Project\Configuration;
use Deriver\Query\Budget;
use Deriver\Reference\SourceRef;
use Deriver\Result\Derivation;
use Deriver\Value\Term;

/**
 * Query-local expansion accounting; residuals are part of the ordinary graph.
 * @visibility root
 */
final class Context
{
    /**
     * Query-local dependency expansion accounting.
     */
    public readonly Resources $resources;
    /**
     * Query-local dependency expansion accounting.
     */
    public int $referenceExpansions = 0;
    /**
     * Query-local dependency expansion accounting.
     */
    public int $bodyExpansions = 0;
    /**
     * Query-local dependency expansion accounting.
     */
    public int $modelApplications = 0;
    /**
     * Query-local dependency expansion accounting.
     */
    public int $sharedNodeHits = 0;
    /**
     * Query-local dependency expansion accounting.
     */
    public int $constructedNodes = 0;
    /**
     * @var array<string, int>
     */
    public array $bodies = [];
    /**
     * @var array<string, int>
     */
    public array $references = [];
    /**
     * @var array<string, Derivation>
     */
    public array $evidence = [];
    /**
     * @var array<string, true>
     */
    public array $active = [];
    /**
     * @var array<string, Term>
     */
    public array $values = [];
    /**
     * @var array<string, Frame>
     */
    public array $frames = [];
    /**
     * @var array<string, list<Frame>> Explicit input bindings by declaration
     */
    public array $entryFrames = [];
    /**
     * Semantic scope and work bounds for retained partial dependency evaluations.
     */
    public string $cacheNamespace = '';
    /**
     * Query-local dependency expansion accounting.
     */
    public ?string $stopReason = null;

    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly Index $index, public readonly Configuration $configuration, public readonly Registry $models, public readonly Budget $budget, public readonly Cache $cache)
    {
        $this->resources = new Resources($configuration->resources);
    }

    /**
     * Reports depth or resource boundaries before expanding a reference.
     */
    public function boundary(int $depth): ?string
    {
        if ($depth <= 0) {
            return 'DEPTH_LIMIT';
        }
        $reason = $this->resources->reason(call: true);
        if ($reason === 'STACK_LIMIT') {
            return $reason;
        }
        $this->stopReason ??= $reason;
        if ($this->referenceExpansions >= $this->budget->transfers || $this->constructedNodes >= $this->budget->nodes) {
            $this->stopReason ??= 'BUDGET_EXCEEDED';
        }
        return $this->stopReason;
    }

    /**
     * Retains a reference identity, scope, source position, type, and stopping reason.
     */
    public function reference(Frame $frame, string $name, SourceRef $source, string $type = 'mixed', string $reason = 'EXTERNAL_INPUT', string $kind = 'reference'): Term
    {
        return new Term($kind, $name, attributes: [
            'identity' => $frame->identity . ':' . $name . ':' . $source->start,
            'scope' => $frame->graph->body->symbol, 'context' => $frame->identity,
            'source' => $source->path, 'start' => $source->start, 'end' => $source->end,
            'type' => $type, 'reason' => $reason,
        ]);
    }

    /**
     * Records the definition requested by dependency expansion.
     */
    public function record(Frame $frame, Instruction $instruction): void
    {
        $key = $frame->identity . ':' . $instruction->id;
        $this->evidence[$key] = new Derivation($key, 'dependency', $instruction->source, array_map(static fn (string $register): string => $frame->identity . ':' . $register, $instruction->operands), $instruction->operation);
        $this->constructedNodes++;
    }
}
