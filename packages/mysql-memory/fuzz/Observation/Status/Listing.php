<?php

declare(strict_types=1);

namespace Fuzz\Observation\Status;

use Fuzz\Observation\Observer;
use Fuzz\Observation\Record;
use Fuzz\Observation\Traffic;
use PDO;

/**
 * Compares every row of a complete status listing under independent baselines.
 * Session command totals stay exact; global totals retain their SQL-history delta.
 * Traffic is checked against mysqlnd's independent execution-boundary measurements.
 * Only named environmental values use domain validation.
 * Columns, row identities, conditions and all unclassified values remain exact.
 */
final class Listing
{
    /**
     * Identifies the validation rules recorded with a normalized observation.
     */
    public const CONTRACT = 'status-history-wire-and-environment-domains';

    /**
     * @param Snapshot $before Independently observed state before the target
     * @param bool $global Whether the target asks for global command counters
     */
    public function __construct(public readonly Snapshot $before, public readonly bool $global, public readonly Traffic $traffic = new Traffic())
    {
    }

    /**
     * Selects exactly one complete listing, without filters or additional statements.
     */
    public static function handles(string $sql): bool
    {
        return preg_match('/\A\s*SHOW\s+(?:(?:GLOBAL|SESSION|LOCAL)\s+)?STATUS\s*;?\s*\z/i', $sql) === 1;
    }

    /**
     * Captures the independent counter and transport baselines.
     */
    public static function capture(PDO $pdo, string $sql, ?Record $record = null): self
    {
        return new self(Snapshot::capture($pdo, $record), preg_match('/\bGLOBAL\b/i', $sql) === 1);
    }

    /**
     * @param array<string, mixed> $observation The complete PDO observation
     * @return array<string, mixed> A validated observation, or the unchanged raw result
     */
    public function comparable(array $observation): array
    {
        $results = $observation['results'] ?? null;
        if (isset($observation['error']) || !is_array($results) || count($results) !== 1 || !is_array($results[0] ?? null)) {
            return $observation;
        }
        $columns = $results[0]['columns'] ?? null;
        $rows = $results[0]['rows'] ?? null;
        if (!is_array($columns) || array_column($columns, 0) !== ['Variable_name', 'Value'] || !is_array($rows)) {
            return $observation;
        }
        $values = Snapshot::index($rows);
        $expected = $this->global ? $this->before->global : $this->before->session;
        if ($values === null || array_keys($values) !== array_keys($expected)) {
            return $observation;
        }
        $policy = new Policy();
        $comparable = [];
        foreach ($values as $name => $value) {
            $quantity = $policy->value($name, $value, $this->global, $this->before, $this->traffic, microtime(true) - $this->before->instant);
            if ($quantity === null) {
                return $observation;
            }
            $comparable[] = [$name, $quantity];
        }
        $results[0]['rows'] = (new Observer())->bag($comparable);
        $observation['results'] = $results;
        $observation['contracts'] = [self::CONTRACT];

        return $observation;
    }
}
