<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Status;

use Fuzz\Target\Status\Policy;
use Fuzz\Target\Status\Snapshot;
use mysqli;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowStatus;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Scalar;

/**
 * Applies the shared status rules to original semantic structures.
 * Session history remains exact; global history is compared from independent
 * baselines. Native wire bytes are measured, while a direct call must report
 * zero transport bytes. Physical values follow the shared empty-value policy.
 */
final class Listing
{
    /**
     * The validated direct-call comparison contract recorded with the raw observations.
     */
    public const CONTRACT = 'status-history-native-wire-direct-empty-transport-and-physical-values';

    /**
     * Holds independent baselines and the native request's transport length.
     */
    public function __construct(public readonly Snapshot $native, public readonly Snapshot $memory, public readonly bool $global, public readonly string $sql)
    {
    }

    /**
     * Selects complete status listings only, retaining all variables and metadata.
     */
    public static function capture(Statement|Scalar $node, mysqli $connection, Session $session, string $sql): ?self
    {
        if (!$node instanceof ShowStatus || $node->filter !== null) {
            return null;
        }
        $snapshots = new Snapshots();
        $native = $snapshots->native($connection);
        $memory = $snapshots->memory($session);

        return $native === null || $memory === null ? null : new self($native, $memory, $node->scope === VariableScope::Global, $sql);
    }

    /**
     * @param array<string, mixed> $observation The complete direct protocol observation
     * @return array<string, mixed> The validated comparison copy, or the original on any refusal
     */
    public function comparable(array $observation, bool $memory): array
    {
        $results = $observation['results'] ?? null;
        if (isset($observation['error']) || !is_array($results) || count($results) !== 1 || !is_array($results[0] ?? null)) {
            return $observation;
        }
        $columns = $results[0]['columns'] ?? null;
        $rows = $results[0]['rows'] ?? null;
        if (!is_array($columns) || array_column($columns, 'name') !== ['Variable_name', 'Value'] || !is_array($rows)) {
            return $observation;
        }
        $before = $memory ? $this->memory : $this->native;
        $values = Snapshot::index($rows);
        if ($values === null || array_keys($values) !== array_keys($this->global ? $before->global : $before->session)) {
            return $observation;
        }
        $policy = new Policy();
        $compared = [];
        foreach ($values as $name => $value) {
            $quantity = $memory && $policy->kind($name, $this->global) === 'wire'
                ? ($value === '0' && ($before->session[$name] ?? null) === '0' && ($before->global[$name] ?? null) === '0' ? '<measured-wire-bytes>' : null)
                : $policy->value($name, $value, $this->global, $memory, $before, $this->sql, microtime(true) - $before->instant);
            if ($quantity === null) {
                return $observation;
            }
            $compared[] = [$name, $quantity];
        }
        $results[0]['rows'] = $compared;
        $observation['results'] = $results;
        $observation['contracts'] = [self::CONTRACT];

        return $observation;
    }
}
