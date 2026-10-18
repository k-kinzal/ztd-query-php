<?php

declare(strict_types=1);

namespace Deriver\Deriver\Publication;

use Deriver\Deriver\Evaluator\Evidence\Node;
use Deriver\Deriver\Evaluator\Index\DefinitionIndex;
use Deriver\Deriver\Evaluator\Target;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Identity\QueryEncoding;

/**
 * The evidence root of one candidate: the selected target, independently of the value expansion reached.
 * @visibility parent
 */
final class ObservationRoot
{
    /**
     * Identifies the selected target above the value's own derivation forest.
     */
    public function of(\Deriver\Deriver\Evaluator\Request\Query $query, Target $target, Term $value, DefinitionIndex $index, \Deriver\Deriver\Evaluator\Evidence\Provenance $provenance): Node
    {
        $source = $target->source ?? $index->graph($target->owner)?->body->source;
        return new Node(\Deriver\Deriver\Evaluator\Evidence\EvidenceKind::Observation, ['value' => (new \Deriver\Deriver\Evaluator\Evidence\Forest($provenance))->root($value)], $source, ['query' => (new QueryEncoding())->key($query), 'owner' => $target->owner, 'target' => $target->selector, 'role' => $query::class]);
    }
}
