<?php

declare(strict_types=1);

namespace Deriver\Result\Serialization;

use Deriver\Result\DerivationResult;
use Deriver\Result\Frontier;
use Deriver\Result\StorageSnapshot;
use JsonException;

/**
 * Serializes a derivation as versioned JSON without native PHP object serialization.
 *
 * @visibility public
 * @example Encoding a symbolic term as a result uses DerivationResult::toJson()
 *     (new \Deriver\Query\ReturnQuery('run'))->symbol // => 'run'
 */
final class JsonReport
{
    /**
     * Produces lossless JSON with shared graph references.
     * @param DerivationResult $result Derived result
     * @param bool $includeSecrets Explicitly reveal confidential inputs
     * @return string Versioned JSON document
     * @throws JsonException If metadata cannot be encoded as JSON
     */
    public function render(DerivationResult $result, bool $includeSecrets = false): string
    {
        $graph = new ValueGraph($includeSecrets);
        $normal = [];
        foreach ($result->normalOutcomes as $alternative) {
            $normal[] = ['guard' => (object) $alternative->guard, 'values' => (object) $graph->mapping($alternative->values), 'state' => (object) $graph->mapping($alternative->state), 'evidence' => $alternative->evidence, 'storage' => $this->storage($alternative->storage, $graph)];
        }
        $exceptional = [];
        foreach ($result->exceptionalOutcomes as $alternative) {
            $exceptional[] = ['guard' => (object) $alternative->guard, 'exception' => $graph->add($alternative->exception), 'state' => (object) $graph->mapping($alternative->state), 'evidence' => $alternative->evidence, 'storage' => $this->storage($alternative->storage, $graph)];
        }
        $frontiers = $this->frontiers($result->frontiers, $graph);
        $diagnostics = $this->frontiers($result->projectDiagnostics, $graph);
        $query = (new QueryEncoding())->record($result->query, $graph);
        $document = [
            'schemaVersion' => $result->schemaVersion,
            'snapshotId' => $result->snapshotId,
            'resultId' => $result->reference->id,
            'query' => $query,
            'normalOutcomes' => $normal,
            'exceptionalOutcomes' => $exceptional,
            'reachability' => $result->reachability,
            'assessment' => $result->assessment,
            'frontiers' => $frontiers,
            'projectDiagnostics' => $diagnostics,
            'assumptions' => $result->assumptions,
            'evidence' => (object) $result->evidence,
            'values' => (object) $graph->records,
            'statistics' => $result->statistics,
        ];
        return json_encode((new JsonText())->tree($document), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    }

    /**
     * Serializes diagnostic residuals as shared value references.
     * @param list<Frontier> $frontiers Relevant or project-wide diagnostics
     * @param ValueGraph $graph Shared term encoding
     * @return list<array{code: string, at: \Deriver\Reference\SourceRef, operation: string, affectedProjections: list<string>, knownDependencies: list<string>, residual: string|null, missingCapability: string}> Diagnostic records
     */
    public function frontiers(array $frontiers, ValueGraph $graph): array
    {
        $records = [];
        foreach ($frontiers as $frontier) {
            $records[] = ['code' => $frontier->code, 'at' => $frontier->at, 'operation' => $frontier->operation, 'affectedProjections' => $frontier->affectedProjections, 'knownDependencies' => $frontier->knownDependencies, 'residual' => $frontier->residual === null ? null : $graph->add($frontier->residual), 'missingCapability' => $frontier->missingCapability];
        }
        return $records;
    }

    /**
     * Encodes raw storage roots through the same lossless value table.
     * @param StorageSnapshot $storage Reachable storage
     * @param ValueGraph $graph Shared term encoding
     * @return array{bindings: object, cells: object} Storage references
     */
    public function storage(StorageSnapshot $storage, ValueGraph $graph): array
    {
        return ['bindings' => (object) $graph->mapping($storage->bindings), 'cells' => (object) $graph->mapping($storage->cells)];
    }
}
