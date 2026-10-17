<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit;

use Fuzz\Target\EngineReports;
use Fuzz\Target\MutexReports;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineMutex;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineStatus;
use SqlSemantics\Statement\Scalar;

/**
 * Applies the existing physical-engine report contract to direct protocol observations.
 * All metadata, diagnostics and unrelated statements remain exact. The memory
 * report must be empty; the native payload must satisfy the shared report validator.
 */
final class EngineReport
{
    /**
     * @param array<string, mixed> $observation The unchanged direct observation
     * @return array<string, mixed> A comparison copy with the explicitly validated row exception
     */
    public function comparable(Statement|Scalar $node, array $observation, bool $memory): array
    {
        if (!$node instanceof ShowEngineStatus && !$node instanceof ShowEngineMutex) {
            return $observation;
        }
        $mutex = $node instanceof ShowEngineMutex;
        if (!in_array(strtoupper($node->engine->value ?? 'ALL'), $mutex ? ['ALL', 'INNODB'] : ['ALL', 'INNODB', 'PERFORMANCE_SCHEMA'], true)) {
            return $observation;
        }
        $results = $observation['results'] ?? null;
        if (isset($observation['error']) || !is_array($results) || count($results) !== 1 || !is_array($results[0] ?? null)) {
            return $observation;
        }
        $columns = $results[0]['columns'] ?? null;
        $rows = $results[0]['rows'] ?? null;
        if (!is_array($columns) || count($columns) !== 3 || !is_array($rows)) {
            return $observation;
        }
        foreach (['Type', 'Name', 'Status'] as $index => $name) {
            if (!is_array($columns[$index] ?? null) || ($columns[$index]['name'] ?? null) !== $name || ($columns[$index]['orgname'] ?? null) !== '' || ($columns[$index]['type'] ?? null) !== 253) {
                return $observation;
            }
        }
        if ($memory ? $rows !== [] : !($mutex ? (new MutexReports())->native($rows) : (new EngineReports())->native($rows))) {
            return $observation;
        }
        $results[0]['rows'] = [];
        $observation['results'] = $results;
        $observation['contracts'] = [$mutex ? MutexReports::CONTRACT : EngineReports::CONTRACT];

        return $observation;
    }
}
