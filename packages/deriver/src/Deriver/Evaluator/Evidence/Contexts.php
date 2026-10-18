<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Evidence;

/**
 * Projects call and binding dependencies without inventing execution paths.
 * @visibility parent
 * @visibility Deriver\Deriver\Publication
 */
final class Contexts
{
    /**
     * @var array<string, Node>
     */
    private array $memo = [];

    /**
     * Projects the shared derivation graph to caller and binding relationships.
     */
    public function project(Node $node): Node
    {
        if (isset($this->memo[$node->id])) {
            return $this->memo[$node->id];
        }
        $inputs = [];
        foreach ($node->inputs as $input) {
            $context = $this->project($input);
            if ($context->kind !== EvidenceKind::ContextAny || $node->kind === EvidenceKind::Choice) {
                $inputs[$context->id] = $context;
            }
        }
        if (in_array($node->kind, [EvidenceKind::Call, EvidenceKind::ArgumentBinding], true)) {
            $context = new Node(EvidenceKind::ContextCall, $inputs, $node->source, $node->attributes);
        } elseif ($inputs === []) {
            $context = new Node(EvidenceKind::ContextAny);
        } elseif (count($inputs) === 1) {
            $context = reset($inputs);
        } else {
            $context = new Node($node->kind === EvidenceKind::Choice ? EvidenceKind::ContextOr : EvidenceKind::ContextAnd, $inputs);
        }
        return $this->memo[$node->id] = $context;
    }
}
