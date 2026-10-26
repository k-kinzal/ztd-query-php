<?php

declare(strict_types=1);

namespace Fuzz\Observation;

use Fuzz\Fixture\Tables;
use PDO;
use RuntimeException;

/**
 * Captures session-owned metadata locks without issuing SQL on the observed session.
 *
 * Object identities, modes, durations and statuses describe held SQL state. Server
 * pointers, source-code locations and event numbers are not part of this projection.
 * This inventory does not establish row locking, advisory recursion or contention.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-metadata-locks-table.html.
 */
final class MetadataLocks
{
    /**
     * Enables the instrument before the tested statement; 5.7 may disable it by default.
     *
     * This setup must never run between the statement and capture: enabling an
     * instrument afterwards cannot recover locks that were not recorded.
     * @phpstan-impure
     */
    public function prepare(PDO $administrator, int $connection, ?Record $record = null): void
    {
        (new Tables())->execute($administrator, "UPDATE performance_schema.setup_instruments SET ENABLED='YES' WHERE NAME='wait/lock/metadata/sql/mdl'", $record);
        $this->verify($administrator, (new OwnerThread())->resolve($administrator, $connection, $record), $record);
    }

    /**
     * Reads the owner immediately after primary results and conditions, before owner probes.
     *
     * Raw order is retained; only the comparison uses a multiset with duplicates.
     * @phpstan-impure
     * @return array{raw: array<string, mixed>, comparable: array<string, mixed>}
     * @throws RuntimeException When ownership, instrumentation or the inventory cannot be read
     */
    public function capture(PDO $administrator, int $connection, string $version, ?Record $record = null): array
    {
        $thread = (new OwnerThread())->resolve($administrator, $connection, $record);
        $this->verify($administrator, $thread, $record);
        $columns = 'OBJECT_TYPE, OBJECT_SCHEMA, OBJECT_NAME, ' . (str_starts_with($version, '5.7.') ? '' : 'COLUMN_NAME, ') . 'LOCK_TYPE, LOCK_DURATION, LOCK_STATUS';
        $observer = new Observer();
        $raw = $observer->probe($administrator, 'SELECT ' . $columns . ' FROM performance_schema.metadata_locks WHERE OWNER_THREAD_ID = ' . $thread, $record);
        $results = $raw['results'];
        if (!is_array($results) || count($results) !== 1 || !is_array($results[0]) || !is_array($results[0]['rows'] ?? null)) {
            throw new RuntimeException('The observed metadata locks did not return one row set.');
        }
        $results[0]['rows'] = $observer->bag($results[0]['rows']);
        $comparable = $raw;
        $comparable['results'] = $results;
        $this->verify($administrator, $thread, $record);
        return ['raw' => $raw, 'comparable' => $comparable];
    }

    /**
     * Refuses missing, disabled or exhausted instrumentation instead of claiming no locks.
     *
     * The loss counter must stay zero: earlier unrecorded locks may still be held.
     * @phpstan-impure
     * @throws RuntimeException When the metadata lock inventory cannot be trusted
     */
    public function verify(PDO $administrator, int $thread, ?Record $record = null): void
    {
        $queries = [
            "SELECT ENABLED FROM performance_schema.setup_instruments WHERE NAME='wait/lock/metadata/sql/mdl'" => [['YES']],
            "SELECT ENABLED FROM performance_schema.setup_consumers WHERE NAME IN ('global_instrumentation', 'thread_instrumentation') ORDER BY NAME" => [['YES'], ['YES']],
            'SELECT INSTRUMENTED FROM performance_schema.threads WHERE THREAD_ID = ' . $thread => [['YES']],
        ];
        $observer = new Observer();
        foreach ($queries as $sql => $expected) {
            if ($observer->rows($administrator, $sql, $record) !== $expected) {
                throw new RuntimeException('Metadata lock instrumentation is missing or disabled.');
            }
        }
        if ($observer->rows($administrator, "SHOW GLOBAL STATUS LIKE 'Performance_schema_metadata_lock_lost'", $record) !== [['Performance_schema_metadata_lock_lost', '0']]) {
            throw new RuntimeException('Metadata lock instrumentation has lost records or its loss counter is unreadable.');
        }
    }
}
