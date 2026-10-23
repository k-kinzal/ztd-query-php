<?php

declare(strict_types=1);

namespace Fuzz\Target\Status;

use Fuzz\Target\Observer;
use PDO;

/**
 * Compares every row of a complete status listing under independent baselines.
 * Session command totals stay exact; global totals retain their SQL-history delta.
 * Traffic is checked against mysqlnd's independently measured reply bytes and the
 * actual query packet size. Physical exceptions require memory's empty/zero value.
 * Columns, row identities, conditions and all unclassified values remain exact.
 */
final class Listing
{
    /**
     * Identifies the validation rules recorded with a normalized observation.
     */
    public const CONTRACT = 'status-history-deltas-measured-wire-and-empty-physical-values';

    /**
     * @param Snapshot $before Independently observed state before the target
     * @param string $sql The complete listing submitted by the generator
     * @param bool $global Whether the target asks for global command counters
     */
    public function __construct(public readonly Snapshot $before, public readonly string $sql, public readonly bool $global)
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
    public static function capture(PDO $pdo, string $sql): ?self
    {
        $before = Snapshot::capture($pdo);

        return $before === null ? null : new self($before, $sql, preg_match('/\bGLOBAL\b/i', $sql) === 1);
    }

    /**
     * @param array<string, mixed> $observation The complete PDO observation
     * @return array<string, mixed> A validated observation, or the unchanged raw result
     */
    public function comparable(array $observation, bool $memory): array
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
            $quantity = $policy->value($name, $value, $this->global, $memory, $this->before, $this->sql, microtime(true) - $this->before->instant);
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
