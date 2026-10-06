<?php

declare(strict_types=1);

namespace Deriver\Result\Evidence;

use Deriver\Project\ProjectSnapshot;

/**
 * One derivation and its call context, retained independently of a session.
 * @example Inspecting candidate-specific evidence
 *     $session = (new \Deriver\Analyzer())->open(new \Deriver\Project\ProjectInput([new \Deriver\Project\SourceFile('a.php', '<?php function f(){return 42;}')]));
 *     $proof = $session->derive(new \Deriver\Query\ReturnQuery('f'))->candidates[0]->evidence[0];
 *     $proof->root->kind // => 'observation'
 * @visibility public
 */
final class Alternative
{
    /**
     * Owns a derivation root, context root and immutable snapshot manifest.
     */
    public function __construct(public readonly Node $root, public readonly Node $contextRoot, public readonly ProjectSnapshot $snapshot)
    {
    }

    /**

     * @return array<string, Node> All nodes required to resolve every edge

     */
    public function nodes(): array
    {
        $nodes = [];
        /** @var list<Node> $pending */
        $pending = [$this->root, $this->contextRoot];
        while ($pending !== []) {
            $node = array_pop($pending);
            if (isset($nodes[$node->id])) {
                continue;
            }
            $nodes[$node->id] = $node;
            array_push($pending, ...array_values($node->inputs));
        }
        ksort($nodes);
        return $nodes;
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
            if ($node->kind === 'context-any') {
                return true;
            }
            if ($node->kind === 'context-any') {
                return true;
            }
            if ($node->kind === 'context-call') {
                $constrained = true;
                if (strcasecmp((string) ($node->attributes['caller'] ?? ''), $caller) === 0) {
                    return true;
                }
            }
            array_push($pending, ...array_values($node->inputs));
        }
        return !$constrained;
    }

    /**

     * @return array<string, mixed> Self-contained evidence export

     */
    public function toArray(): array
    {
        $nodes = [];
        foreach ($this->nodes() as $id => $node) {
            $inputs = [];
            foreach ($node->inputs as $role => $input) {
                $inputs[] = ['role' => $role, 'node' => $input->id];
            }
            $source = $node->source === null ? null : [...get_object_vars($node->source), 'file_hash' => $this->snapshot->sources[$node->source->path] ?? null];
            $nodes[$id] = ['kind' => $node->kind, 'inputs' => $inputs, 'source' => $source, 'attributes' => (object) $node->attributes];
        }
        return ['root' => $this->root->id, 'context_root' => $this->contextRoot->id, 'nodes' => (object) $nodes, 'snapshot' => $this->snapshot];
    }
}
