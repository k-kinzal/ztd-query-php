<?php

declare(strict_types=1);

namespace Fuzz\Observation;

use PDO;
use RuntimeException;

/**
 * Observes persistent SQL effects through an independent administrator connection.
 *
 * The public MySQL catalogs select the probes, independently of MemoryDB's semantic
 * classification. Failed reads abort the input. Raw values remain available, and
 * unclassified differences remain findings, including environment-dependent values
 * for which no comparison contract has yet been established.
 */
final class CatalogState
{
    /**
     * Records application databases and the release's account, program and server catalogs.
     *
     * @return array{raw: array<string, mixed>, comparable: array<string, mixed>}
     * @throws RuntimeException When an observed catalog cannot be read
     */
    public function capture(PDO $connection, string $version, ?Record $record = null): array
    {
        $observer = new Observer();
        $raw = [];
        $comparable = [];
        foreach ($this->queries($version) as $name => $sql) {
            $observation = $observer->probe($connection, $sql, $record);
            $raw[$name] = $observation;
            $results = $observation['results'];
            if (!is_array($results) || count($results) !== 1 || !is_array($results[0]) || !is_array($results[0]['rows'] ?? null)) {
                throw new RuntimeException('The observed catalog ' . $name . ' did not return one row set.');
            }
            $results[0]['rows'] = $observer->bag($results[0]['rows']);
            $observation['results'] = $results;
            $comparable[$name] = $observation;
        }
        $databases = $this->databases($connection, $record);
        $raw['databases'] = $databases;
        $comparable['databases'] = $databases;

        return ['raw' => $raw, 'comparable' => $comparable];
    }

    /**
     * Includes committed rows outside the conventional fz fixture as well as their definitions.
     * Virtual server schemas are observed through the named catalog probes instead.
     *
     * @return array<string, array{definitions: array<string, mixed>|null, rows: array<string, mixed>}>
     * @throws RuntimeException When the database inventory cannot be read
     */
    public function databases(PDO $connection, ?Record $record = null): array
    {
        $observer = new Observer();
        $inventory = $observer->rows($connection, 'SHOW DATABASES', $record);
        $databases = [];
        foreach ($inventory as $row) {
            if (!is_array($row) || !is_string($row[0] ?? null)) {
                throw new RuntimeException('The database inventory returned an invalid identifier.');
            }
            $name = $row[0];
            if (!in_array($name, ['information_schema', 'performance_schema', 'mysql', 'sys'], true)) {
                $databases[$name] = ['definitions' => $observer->definitions($connection, $name, $record), 'rows' => $observer->tables($connection, $name, $record)];
            }
        }
        ksort($databases);
        return $databases;
    }

    /**
     * Uses versioned public interfaces, never an emulator capability allowlist.
     *
     * Sources: https://dev.mysql.com/doc/refman/8.4/en/system-schema.html,
     * https://dev.mysql.com/doc/refman/8.4/en/replica-logs-status.html.
     *
     * @return array<string, string>
     */
    public function queries(string $version): array
    {
        $queries = [];
        foreach (['SCHEMATA', 'ROUTINES', 'PARAMETERS', 'TRIGGERS', 'EVENTS', 'PLUGINS'] as $table) {
            $queries['information_schema.' . $table] = 'SELECT * FROM information_schema.' . $table;
        }
        foreach (['user', 'db', 'tables_priv', 'columns_priv', 'procs_priv', 'proxies_priv', 'servers', 'slave_master_info', 'slave_relay_log_info'] as $table) {
            $queries['mysql.' . $table] = 'SELECT * FROM mysql.' . $table;
        }
        $queries['replica-status'] = str_starts_with($version, '5.') ? 'SHOW SLAVE STATUS' : 'SHOW REPLICA STATUS';
        $queries['prepared-transactions'] = 'XA RECOVER';
        if (!str_starts_with($version, '5.6.')) {
            $queries['replication-connections'] = 'SELECT * FROM performance_schema.replication_connection_configuration';
            $table = str_starts_with($version, '5.7.') ? 'INNODB_SYS_TABLESPACES' : 'INNODB_TABLESPACES';
            $queries['tablespaces'] = 'SELECT NAME, ROW_FORMAT, PAGE_SIZE, SPACE_TYPE FROM information_schema.' . $table;
        }
        if (!str_starts_with($version, '5.')) {
            foreach (['role_edges', 'default_roles', 'global_grants', 'password_history'] as $table) {
                $queries['mysql.' . $table] = 'SELECT * FROM mysql.' . $table;
            }
            $queries['persisted-settings'] = 'SELECT * FROM performance_schema.persisted_variables';
            $queries['resource-groups'] = 'SELECT * FROM information_schema.RESOURCE_GROUPS';
            $queries['spatial-references'] = 'SELECT * FROM information_schema.ST_SPATIAL_REFERENCE_SYSTEMS';
        }
        return $queries;
    }
}
