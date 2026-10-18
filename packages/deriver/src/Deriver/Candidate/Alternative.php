<?php

declare(strict_types=1);

namespace Deriver\Deriver\Candidate;

use Deriver\Deriver\Evaluator\Evidence\Node;

/**
 * One derivation and its call context, retained independently of the deriver.
 * @example Inspecting candidate-specific evidence
 *     $path = tempnam(sys_get_temp_dir(), 'deriver-example-');
 *     file_put_contents($path, '<?php function f(){return 42;}');
 *     $deriver = \Deriver\Deriver::prepare([$path]);
 *     unlink($path);
 *     $proof = $deriver->derive('f#return')[0]->evidence[0];
 *     $proof->root->kind === \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::Observation // => true
 * @visibility public
 */
final class Alternative
{
    /**
     * Owns a derivation root, context root and immutable snapshot manifest.
     */
    public function __construct(public readonly Node $root, public readonly Node $contextRoot, public readonly Snapshot $snapshot)
    {
    }

    /**

     * @return list<Node> The derivation in dependency traversal order, sharing repeated subtrees

     */
    public function nodes(): array
    {
        $nodes = [];
        /** @var list<Node> $pending */
        $pending = [$this->root];
        while ($pending !== []) {
            $node = array_pop($pending);
            if (isset($nodes[$node->id])) {
                continue;
            }
            $nodes[$node->id] = $node;
            array_push($pending, ...array_reverse(array_values($node->inputs)));
        }
        return array_values($nodes);
    }

    /**

     * Keeps unconstrained contexts and contexts containing the selected caller.

     */
    public function matchesCaller(string $caller): bool
    {
        $pending = [$this->contextRoot];
        $seen = [];
        $constrained = false;
        while ($pending !== []) {
            $node = array_pop($pending);
            if (isset($seen[$node->id])) {
                continue;
            }
            $seen[$node->id] = true;
            if ($node->kind === \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::ContextAny) {
                return true;
            }
            if ($node->kind === \Deriver\Deriver\Evaluator\Evidence\EvidenceKind::ContextCall) {
                $constrained = true;
                if (strcasecmp((string) ($node->attributes['caller'] ?? ''), $caller) === 0) {
                    return true;
                }
            }
            array_push($pending, ...array_values($node->inputs));
        }
        return !$constrained;
    }

}
