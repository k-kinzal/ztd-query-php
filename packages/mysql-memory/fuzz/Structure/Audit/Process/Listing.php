<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Process;

use Fuzz\Target\Observer;
use Fuzz\Target\Process\Listing as ProcessListing;
use Fuzz\Target\Process\Sample;
use mysqli;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowProcesslist;
use SqlSemantics\Statement\Scalar;

/**
 * Validates each server's process identities, ports and clocks independently.
 * The direct interface must have no TCP peer port. Request text stays exact.
 */
final class Listing
{
    /**
     * Independent process identity and clock checks with an empty direct transport port.
     */
    public const CONTRACT = 'process-identities-and-clocks-with-no-direct-transport-port';

    /**
     * Holds independent before samples and the connections used for after samples.
     */
    public function __construct(public readonly Sample $native, public readonly Sample $memory, public readonly mysqli $connection, public readonly Session $session)
    {
    }

    /**
     * Sets the observable request label out of band, without parsing it in memory.
     * All other roots retain the invalid label that detects source dependence.
     */
    public static function capture(Statement|Scalar $node, mysqli $connection, Session $session, string $request): ?self
    {
        if (!$node instanceof ShowProcesslist) {
            return null;
        }
        $session->text = $request;
        $snapshots = new Snapshots();
        $native = $snapshots->native($connection);
        $memory = $snapshots->memory($session);

        return $native === null || $memory === null ? null : new self($native, $memory, $connection, $session);
    }

    /**
     * @param array<string, mixed> $observation
     * @return array<string, mixed>
     */
    public function comparable(array $observation, bool $memory): array
    {
        $after = $memory ? (new Snapshots())->memory($this->session) : (new Snapshots())->native($this->connection);

        return $after === null ? $observation : self::validated($observation, $memory ? $this->memory : $this->native, $after, $memory);
    }

    /**
     * Preserves raw output unless every row satisfies the shared process contract.
     *
     * @param array<string, mixed> $observation
     * @return array<string, mixed>
     */
    public static function validated(array $observation, Sample $before, Sample $after, bool $direct): array
    {
        $results = $observation['results'] ?? null;
        if (isset($observation['error']) || !is_array($results) || count($results) !== 1 || !is_array($results[0] ?? null)) {
            return $observation;
        }
        $columns = $results[0]['columns'] ?? null;
        $rows = $results[0]['rows'] ?? null;
        if (!is_array($columns) || array_column($columns, 'name') !== ['Id', 'User', 'Host', 'db', 'Command', 'Time', 'State', 'Info'] || !is_array($rows)) {
            return $observation;
        }
        $indexed = (new Snapshots())->indexed($rows);
        $compared = $indexed === null ? null : (new ProcessListing($before, null, $direct))->rows($indexed, $after);
        if ($compared === null) {
            return $observation;
        }
        $results[0]['rows'] = (new Observer())->bag($compared);
        $observation['results'] = $results;
        $observation['contracts'] = [self::CONTRACT];

        return $observation;
    }
}
