<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Explain;

use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Statement\Scalar;

/**
 * Compares one analyzed TABLE scan while validating instance-specific measurements.
 * Row counts, loop counts, table identity, protocol metadata and diagnostics stay
 * exact. Only nonnegative estimates and finite ordered elapsed times may vary.
 */
final class ScanMeasurements
{
    /**
     * Identifies the validated estimates and elapsed-time exception in audit output.
     */
    public const CONTRACT = 'explain-table-scan-estimates-and-elapsed-time';

    /**
     * @param array<string, mixed> $observation The retained complete observation
     * @return array<string, mixed> A comparison copy; invalid reports are unchanged
     */
    public function comparable(Statement|Scalar $node, array $observation, bool $memory): array
    {
        if (!$node instanceof Explain || !$node->analyze || !$node->statement instanceof QueryRoot || !$node->statement->query instanceof ExplicitTable || isset($observation['error'])) {
            return $observation;
        }
        $results = $observation['results'] ?? null;
        if (!is_array($results) || count($results) !== 1 || !is_array($results[0] ?? null)) {
            return $observation;
        }
        $rows = $results[0]['rows'] ?? null;
        if (!is_array($rows) || count($rows) !== 1 || !is_array($rows[0] ?? null) || count($rows[0]) !== 1 || !is_string($rows[0][0] ?? null)) {
            return $observation;
        }
        $text = $this->text($rows[0][0], $node->statement->query->table->name->value, $memory);
        if ($text === null) {
            return $observation;
        }
        $results[0]['rows'] = [[$text]];
        $observation['results'] = $results;
        $observation['contracts'] = [self::CONTRACT];
        return $observation;
    }

    /**
     * Validates a complete single-scan document before replacing its measurements.
     */
    public function text(string $text, string $table, bool $memory): ?string
    {
        $number = '(?:[0-9]+(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?)';
        $prefix = '-> Table scan on ' . $table;
        $pattern = '~\A' . preg_quote($prefix, '~') . '  \(cost=(' . $number . ') rows=(' . $number . ')\) \(actual time=(' . $number . ')\.\.(' . $number . ') rows=([0-9]+) loops=1\)\n\z~';
        if (preg_match($pattern, $text, $parts) !== 1) {
            return null;
        }
        $values = array_map(floatval(...), array_slice($parts, 1, 4));
        if (count(array_filter($values, is_finite(...))) !== 4 || $values[2] > $values[3] || ($memory && ($values[0] !== 0.0 || $values[1] !== (float) $parts[5]))) {
            return null;
        }
        return $prefix . '  (cost=<estimate> rows=<estimate>) (actual time=<elapsed>..<elapsed> rows=' . $parts[5] . " loops=1)\n";
    }
}
