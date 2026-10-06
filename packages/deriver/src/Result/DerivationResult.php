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
        public readonly string $contract = 'execution',
        public readonly ?\Deriver\Value\Term $candidateGraph = null,
    ) {
        $this->schemaVersion = '1';
    }

    /**
     * Selects a single concrete outcome from the explicit execution API.
     * The closed assessment alone does not establish this: a closed result may still carry PHP_WARNING frontiers, exceptional outcomes, or several alternatives.
     * @return Alternative|null The only normal outcome when every observed value is concrete and the result has no frontiers, exceptional outcomes, or project diagnostics; null otherwise
     * @example An undefined variable reads null, but its warning prevents a definite value
     *     $result = (new \Deriver\Analysis\ExecutionSession(new \Deriver\Project\ProjectInput([new \Deriver\Project\SourceFile('a.php', '<?php function f() { return $missing; }')]),new \Deriver\Project\Configuration()))->derive(new \Deriver\Query\ReturnQuery('f'));
     *     [$result->assessment->closure, $result->definite()] // => ['closed', null]
     */
    public function definite(): ?Alternative
    {
        if (count($this->normalOutcomes) !== 1 || $this->exceptionalOutcomes !== [] || $this->frontiers !== [] || $this->projectDiagnostics !== []) {
            return null;
        }
        $outcome = $this->normalOutcomes[0];
        foreach ($outcome->values as $value) {
            if (!$value->isConcrete()) {
                return null;
            }
        }
        return $outcome;
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
