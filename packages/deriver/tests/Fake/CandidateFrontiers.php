<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Project\ProjectSnapshot;
use Deriver\Reference\SourceRef;
use Deriver\Result\Frontier;
use Deriver\Value\Identity;
use Deriver\Value\Term;
use WeakMap;

/**
 * Inspects residual nodes for migration assertions without adding a second result contract.
 */
final class CandidateFrontiers
{
    /**

     * @return list<Frontier>

     */
    public function frontiers(Term $graph, ProjectSnapshot $snapshot): array
    {
        $pending = [$graph];
        $seen = new WeakMap();
        $frontiers = [];
        while ($pending !== []) {
            $node = array_pop($pending);
            if (isset($seen[$node])) {
                continue;
            }
            $seen[$node] = true;
            array_push($pending, ...array_values($node->operands));
            $reason = $node->attributes['reason'] ?? null;
            if (!is_string($reason)) {
                continue;
            }
            $source = new SourceRef($snapshot->id, (string) ($node->attributes['source'] ?? ''), (int) ($node->attributes['start'] ?? 0), (int) ($node->attributes['end'] ?? $node->attributes['start'] ?? 0));
            $identity = (string) ($node->attributes['identity'] ?? (new Identity())->key($node));
            $frontiers[$identity] = new Frontier($reason, $source, (string) $node->literal, ['value'], [$identity], $node, $reason);
        }
        return array_values($frontiers);
    }
}
