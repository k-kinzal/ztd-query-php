<?php

declare(strict_types=1);

namespace Deriver\Analysis\Candidates;

use Deriver\Candidate\Index\DefinitionIndex;
use Deriver\Candidate\Target;
use Deriver\Result\Evidence\Node;
use Deriver\Result\Serialization\QueryEncoding;
use Deriver\Value\Term;
use JsonException;

/**
 * The evidence root of one candidate: the selected target, independently of the value expansion reached.
 * @visibility namespace
 */
final class ObservationRoot
{
    /**
     * Identifies the selected target above the value's own derivation forest.
     * @throws JsonException If query metadata cannot be encoded
     */
    public function of(Target $target, Term $value, DefinitionIndex $index): Node
    {
        $source = $target->source ?? $index->graph($target->owner)?->body->source;
        return new Node('observation', ['value' => (new \Deriver\Candidate\Evidence\Forest())->root($value)], $source, ['query' => (new QueryEncoding())->key($target->query), 'owner' => $target->owner, 'target' => $target->selector, 'role' => $target->query::class]);
    }
}
