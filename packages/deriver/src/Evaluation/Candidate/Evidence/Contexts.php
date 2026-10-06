<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Candidate\Evidence;

use Deriver\Result\Evidence\Node;

/**
 * Projects call and binding dependencies without inventing execution paths.
 * @visibility root
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
            if ($context->kind !== 'context-any' || $node->kind === 'choice') {
                $inputs[$context->id] = $context;
            }
        }
        if (in_array($node->kind, ['call', 'argument-binding'], true)) {
            $context = new Node('context-call', $inputs, $node->source, $node->attributes);
        } elseif ($inputs === []) {
            $context = new Node('context-any');
        } elseif (count($inputs) === 1) {
            $context = reset($inputs);
        } else {
            $context = new Node($node->kind === 'choice' ? 'context-or' : 'context-and', $inputs);
        }
        return $this->memo[$node->id] = $context;
    }
}
