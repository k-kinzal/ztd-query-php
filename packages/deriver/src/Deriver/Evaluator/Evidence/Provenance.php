<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Evidence;

use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Sources\SourceRef;
use WeakMap;

/**
 * Keeps value identity separate from the immutable graph supporting that value.
 * @visibility parent
 * @visibility Deriver\Deriver\Publication
 */
final class Provenance
{
    /**
     * @var WeakMap<Term, Node> Proofs associated with immutable values in this analysis
     */
    private readonly WeakMap $nodes;
    /**
     * @var WeakMap<Term, Node> Structural roots shared only within this analysis
     */
    public readonly WeakMap $roots;

    /**
     * Creates an empty analysis-local proof ledger.
     */
    public function __construct()
    {
        $this->nodes = new WeakMap();
        $this->roots = new WeakMap();
    }

    /**
     * Finds evidence without adding analysis state to a value.
     */
    public function get(Term $value): ?Node
    {
        return $this->nodes[$value] ?? null;
    }

    /**
     * @param array<string, scalar|null> $attributes
     */
    public function wrap(Term $value, EvidenceKind $kind, ?SourceRef $source = null, array $attributes = []): Term
    {
        return (new Choices($this))->map($value, function (Term $leaf) use ($kind, $source, $attributes): Term {
            $inputs = $this->get($leaf) === null ? $this->inputs($leaf->operands) : ['value' => $this->get($leaf)];
            return $this->attach($leaf, new Node($kind, $inputs, $source, $attributes));
        });
    }

    /**
     * Attaches derivation evidence without changing semantic value identity.
     */
    public function attach(Term $value, Node $evidence): Term
    {
        $copy = new Term($value->kind, $value->literal, $value->operands, $value->attributes);
        $this->nodes[$copy] = $evidence;
        return $copy;
    }

    /**
     * @param array<int|string, Term> $values
     * @return array<string, Node>
     */
    public function inputs(array $values): array
    {
        $inputs = [];
        foreach ($values as $key => $value) {
            $inputs['operand:' . $key] = $this->get($value) ?? (new Forest($this))->root($value);
        }
        return $inputs;
    }

    /**
     * @param list<Term> $values
     */
    public function operation(Term $result, array $values, string $operation): Term
    {
        $inputs = $this->inputs($values);
        return (new Choices($this))->map($result, function (Term $leaf) use ($inputs, $operation): Term {
            if ($this->get($leaf) !== null) {
                $inputs['result'] = $this->get($leaf);
            }
            return $this->attach($leaf, new Node(EvidenceKind::Operation, $inputs, attributes: ['operation' => $operation]));
        });
    }
    /**
     * Combines selected evaluator metadata and demanded selection inputs with an output proof.
     */
    public function evaluator(Term $value, ?Node $evaluator): Term
    {
        if ($evaluator === null) {
            return $value;
        }
        return (new Choices($this))->map($value, fn (Term $leaf): Term => $this->attach($leaf, new Node(EvidenceKind::EvaluatorApplication, [...$evaluator->inputs, 'output' => (new Forest($this))->root($leaf)], $evaluator->source, $evaluator->attributes)));
    }

}
