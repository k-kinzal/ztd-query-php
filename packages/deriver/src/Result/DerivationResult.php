<?php

declare(strict_types=1);

namespace Deriver\Result;

use Deriver\Query\Query;
use Deriver\Reference\ResultRef;
use Deriver\Result\Serialization\JsonReport;
use JsonException;

/**
 * Correlated values, exceptional paths, and independent quality assessments.
 *
 * @visibility public
 * @example Reading the result schema version
 *     $result = new \Deriver\Result\DerivationResult(new \Deriver\Reference\ResultRef('r'), 's', new \Deriver\Query\ReturnQuery('run'), [], [], 'unreachable', new \Deriver\Result\Assessment(), [], [], [], new \Deriver\Result\Statistics());
 *     $result->schemaVersion // => '1'
 */
final class DerivationResult
{
    /**
     * Stable JSON result schema version.
     */
    public readonly string $schemaVersion;

    /**
     * @param ResultRef $reference Session-local result identity
     * @param string $snapshotId Source/model/world manifest identity
     * @param Query $query Original immutable query
     * @param list<Alternative> $normalOutcomes Correlated normal values
     * @param list<Exceptional> $exceptionalOutcomes Exceptions with completed effects
     * @param string $reachability may-reach or proven unreachable
     * @param Assessment $assessment Independent quality axes
     * @param list<Frontier> $frontiers Relevant unresolved dependencies
     * @param list<string> $assumptions Explicit semantic assumptions
     * @param array<string, Derivation> $evidence Shared derivation graph
     * @param Statistics $statistics Logical work and measured resources
     * @param list<Frontier> $projectDiagnostics Independent project source diagnostics
     */
    public function __construct(
        public readonly ResultRef $reference,
        public readonly string $snapshotId,
        public readonly Query $query,
        public readonly array $normalOutcomes,
        public readonly array $exceptionalOutcomes,
        public readonly string $reachability,
        public readonly Assessment $assessment,
        public readonly array $frontiers,
        public readonly array $assumptions,
        public readonly array $evidence,
        public readonly Statistics $statistics,
        public readonly array $projectDiagnostics = [],
    ) {
        $this->schemaVersion = '1';
    }

    /**
     * Serializes lossless values and shared graph references with secrets redacted.
     * @param bool $includeSecrets Whether to explicitly reveal confidential values
     * @return string Versioned JSON
     * @throws JsonException If data cannot be represented in JSON
     */
    public function toJson(bool $includeSecrets = false): string
    {
        return (new JsonReport())->render($this, $includeSecrets);
    }
}
