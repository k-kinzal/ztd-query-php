<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Scope;

use Deriver\Deriver\Evaluator\Call\Evaluators;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Request\Budget;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Lowerer\Graph\Instruction;
use Deriver\Deriver\Sources\SourceRef;
use WeakMap;

/**
 * Query-local expansion accounting; residuals are part of the ordinary graph.
 * @visibility parent
 * @visibility Deriver\Deriver\Publication
 */
final class Context
{
    /**
     * @var list<\Deriver\Deriver\Evaluator\Request\EntryPoint> Explicit invocation inputs
     */
    public array $entries = [];
    /**
     * Restricts only the selected observation's invocation ancestry.
     */
    public ?\Deriver\Deriver\Evaluator\Request\CallPath $callPath = null;
    /**
     * @var WeakMap<\Deriver\Deriver\Lowerer\Graph\CallableGraph, \Deriver\Deriver\Evaluator\Evidence\Node> Evaluator selection proofs, separate from compiled graphs
     */
    public readonly WeakMap $evaluationEvidence;
    /**
     * Query-local proof ledger shared by dependency values.
     */
    public readonly \Deriver\Deriver\Evaluator\Evidence\Provenance $provenance;
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
    public int $evaluatorApplications = 0;
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
     * @var array<string, true> \Deriver\Deriver\Lowerer\Graph\Instruction expansions already counted for this query
     */
    public array $recorded = [];
    /**
     * @var array<string, true>
     */
    public array $active = [];
    /**
     * @var array<string, Term>
     */
    public array $values = [];
    /**
     * @var array<string, Term> Shared reaching-definition queries within this context
     */
    public array $storage = [];
    /**
     * @var array<string, Term> Symbolic inputs for demanded loop updates
     */
    public array $loopInputs = [];
    /**
     * @var array<string, Frame> Entry frames for mutually dependent loop updates
     */
    public array $loopFrames = [];
    /**
     * @var array<string, array<string, string>>
     */
    public array $aliases = [];
    /**
     * @var array<string, Frame>
     */
    public array $frames = [];
    /**
     * @var array<string, \Deriver\Deriver\Evaluator\Invocation\Body> Selected implementations for all dependency paths
     */
    public array $implementations = [];
    /**
     * @var array<string, list<Frame>> Explicit input bindings by declaration
     */
    public array $entryFrames = [];
    /**
     * Query-local dependency expansion accounting.
     */
    public ?string $stopReason = null;
    /**
     * Coordinates live dependencies and distributes work across branches.
     */
    public readonly \Deriver\Deriver\Evaluator\Search\Search $search;
    /**
     * Definition and traversal steps charged to the query work limit.
     */
    public int $workUnits = 0;
    /**

     * Shares work between branches and checks the logical exploration limits.

     */
    public function work(): ?string
    {
        $this->search->checkpoint();
        $this->workUnits++;
        if ($this->workUnits > $this->budget->transfers || $this->constructedNodes >= $this->budget->nodes) {
            $this->stopReason ??= 'BUDGET_EXCEEDED';
        }
        return $this->stopReason;
    }

    /**
     * Captures the dependencies used by this component.
     */
    public function __construct(public readonly DefinitionIndex $index, public readonly \Deriver\Deriver\Evaluator\EvaluatorConfig $configuration, public readonly Evaluators $evaluators, public readonly Budget $budget)
    {
        $this->search = new \Deriver\Deriver\Evaluator\Search\Search();
        $this->provenance = new \Deriver\Deriver\Evaluator\Evidence\Provenance();
        $this->evaluationEvidence = new WeakMap();
    }

    /**
     * Reports depth or exploration boundaries before expanding a reference.
     */
    public function boundary(int $depth): ?string
    {
        if ($depth <= 0) {
            return 'DEPTH_LIMIT';
        }
        if ($this->referenceExpansions >= $this->budget->transfers || $this->constructedNodes >= $this->budget->nodes) {
            $this->stopReason ??= 'BUDGET_EXCEEDED';
        }
        return $this->stopReason;
    }

    /**
     * Retains a reference identity, scope, source position, type, and stopping reason.
     */
    public function reference(Frame $frame, string $name, SourceRef $source, string $type = 'mixed', string $reason = 'EXTERNAL_INPUT', \Deriver\Deriver\Evaluator\TermKind $kind = \Deriver\Deriver\Evaluator\TermKind::Reference): Term
    {
        $value = new Term($kind, $name, attributes: [
            'identity' => $frame->identity . ':' . $name . ':' . $source->start,
            'scope' => $frame->graph->body->symbol, 'context' => $frame->identity,
            'source' => $source->path, 'start' => $source->start, 'end' => $source->end,
            'type' => $type, 'reason' => $reason,
        ]);
        return $this->provenance->wrap($value, \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::Unexpanded, $source, ['symbol' => $name, 'owner' => $frame->graph->body->symbol, 'context' => $frame->identity, 'reason' => $reason]);
    }

    /**
     * Records the definition requested by dependency expansion.
     */
    public function record(Frame $frame, Instruction $instruction): void
    {
        $key = $frame->identity . ':' . $instruction->result;
        $this->recorded[$key] = true;
        $this->constructedNodes = count($this->recorded);
    }
}
