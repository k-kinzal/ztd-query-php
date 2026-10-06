<?php

declare(strict_types=1);

namespace Deriver\Result\Serialization\Candidates;

use Deriver\Result\Candidates\CandidateCollection;
use Deriver\Result\Serialization\JsonText;
use Deriver\Result\Serialization\ValueGraph;
use stdClass;

/**
 * Uses native JSON scalars when lossless and the existing PHP graph codec otherwise.
 * @visibility root
 */
final class Encoding
{
    /**
     * @return list<stdClass>
     */
    public function encode(CandidateCollection $collection, bool $includeSecrets = false): array
    {
        $records = [];
        foreach ($collection as $candidate) {
            $term = $candidate->term;
            $graph = new ValueGraph($includeSecrets);
            $root = $graph->add($term);
            $value = $term->literal;
            $native = $term->kind === 'constant' && (!$term->isSecret() || $includeSecrets)
                && (!is_string($value) || preg_match('//u', $value) === 1)
                && (!is_float($value) || is_finite($value))
                && (!is_int($value) || abs($value) <= 9007199254740991);
            $result = $native ? $value : ['kind' => $term->kind, 'root' => $root, 'nodes' => (object) $graph->records];
            $evidence = array_map(static fn ($alternative): object => (object) $alternative->toArray(), $candidate->evidence);
            $records[] = (object) ['type' => $candidate->type, 'type_name' => $candidate->type_name, 'result' => $result, 'evidence' => (new JsonText())->tree($evidence)];
        }
        return $records;
    }
}
