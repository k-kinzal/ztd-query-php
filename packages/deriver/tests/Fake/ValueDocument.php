<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Result\Serialization\JsonText;
use Deriver\Result\Serialization\ValueGraph;
use Deriver\Value\Term;
use JsonException;

/**
 * Encodes a single graph independently from query execution for decoder contracts.
 * @visibility root
 */
final class ValueDocument
{
    /**
     * @param Term $value Root term
     * @param bool $includeSecrets Whether confidential payloads are included
     * @return string Schema version 1 value-table document
     * @throws JsonException If fixture metadata cannot be encoded
     */
    public static function encode(Term $value, bool $includeSecrets = true): string
    {
        $graph = new ValueGraph($includeSecrets);
        $graph->add($value);
        return json_encode((new JsonText())->tree(['schemaVersion' => '1', 'values' => (object) $graph->records]), JSON_THROW_ON_ERROR);
    }
    /**
     * Builds an exponentially large expanded tree using a linear shared graph.
     * @param int $depth Number of shared array layers
     * @param Term $leaf Shared terminal value
     * @return Term Root of the immutable graph
     */
    public static function shared(int $depth, Term $leaf): Term
    {
        for ($level = 0; $level < $depth; $level++) {
            $leaf = Term::array([$leaf, $leaf]);
        }
        return $leaf;
    }
}
