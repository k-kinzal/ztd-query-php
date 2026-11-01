<?php

declare(strict_types=1);

namespace MySqlMemory\System\Server;

use RuntimeException;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Release-specific plugin definitions used by SHOW and INFORMATION_SCHEMA.
 * Shared definitions and explicit release changes have one owner in the implementation.
 *
 * @visibility MySqlMemory
 */
final class PluginDefinitions
{
    /**
     * @var array<string, array{string, string, string, string, string, ?string, ?string, ?string, ?string, string, string}> Common definitions indexed by name
     */
    private const COMMON = [
        'binlog' => ['binlog', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'Oracle Corporation', 'This is a pseudo storage engine to represent the binlog in a transaction', 'GPL', 'FORCE'],
        'sha256_password' => ['sha256_password', '1.1', 'ACTIVE', 'AUTHENTICATION', '2.1', null, null, 'Oracle Corporation', 'SHA256 password authentication', 'GPL', 'FORCE'],
        'caching_sha2_password' => ['caching_sha2_password', '1.0', 'ACTIVE', 'AUTHENTICATION', '2.1', null, null, 'Oracle Corporation', 'Caching sha2 authentication', 'GPL', 'FORCE'],
        'sha2_cache_cleaner' => ['sha2_cache_cleaner', '1.0', 'ACTIVE', 'AUDIT', '4.1', null, null, 'Oracle Corporation', 'Cache cleaner for Caching sha2 authentication', 'GPL', 'FORCE'],
        'daemon_keyring_proxy_plugin' => ['daemon_keyring_proxy_plugin', '1.0', 'ACTIVE', 'DAEMON', '{server}', null, null, 'Oracle', 'A plugin that implements the keyring component services atop of the keyring plugin', 'GPL', 'FORCE'],
        'CSV' => ['CSV', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'Oracle Corporation', 'CSV storage engine', 'GPL', 'FORCE'],
        'MEMORY' => ['MEMORY', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'Oracle Corporation', 'Hash based, stored in memory, useful for temporary tables', 'GPL', 'FORCE'],
        'InnoDB' => ['InnoDB', '{series}', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'Oracle Corporation', 'Supports transactions, row-level locking, and foreign keys', 'GPL', 'FORCE'],
        'INNODB_TRX' => ['INNODB_TRX', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB transactions', 'GPL', 'FORCE'],
        'INNODB_CMP' => ['INNODB_CMP', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression', 'GPL', 'FORCE'],
        'INNODB_CMP_RESET' => ['INNODB_CMP_RESET', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression; reset cumulated counts', 'GPL', 'FORCE'],
        'INNODB_CMPMEM' => ['INNODB_CMPMEM', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compressed buffer pool', 'GPL', 'FORCE'],
        'INNODB_CMPMEM_RESET' => ['INNODB_CMPMEM_RESET', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compressed buffer pool; reset cumulated counts', 'GPL', 'FORCE'],
        'INNODB_CMP_PER_INDEX' => ['INNODB_CMP_PER_INDEX', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression (per index)', 'GPL', 'FORCE'],
        'INNODB_CMP_PER_INDEX_RESET' => ['INNODB_CMP_PER_INDEX_RESET', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression (per index); reset cumulated counts', 'GPL', 'FORCE'],
        'INNODB_BUFFER_PAGE' => ['INNODB_BUFFER_PAGE', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Buffer Page Information', 'GPL', 'FORCE'],
        'INNODB_BUFFER_PAGE_LRU' => ['INNODB_BUFFER_PAGE_LRU', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Buffer Page in LRU', 'GPL', 'FORCE'],
        'INNODB_BUFFER_POOL_STATS' => ['INNODB_BUFFER_POOL_STATS', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Buffer Pool Statistics Information ', 'GPL', 'FORCE'],
        'INNODB_TEMP_TABLE_INFO' => ['INNODB_TEMP_TABLE_INFO', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Temp Table Stats', 'GPL', 'FORCE'],
        'INNODB_METRICS' => ['INNODB_METRICS', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Metrics Info', 'GPL', 'FORCE'],
        'INNODB_FT_DEFAULT_STOPWORD' => ['INNODB_FT_DEFAULT_STOPWORD', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Default stopword list for InnDB Full Text Search', 'GPL', 'FORCE'],
        'INNODB_FT_DELETED' => ['INNODB_FT_DELETED', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS DELETED TABLE', 'GPL', 'FORCE'],
        'INNODB_FT_BEING_DELETED' => ['INNODB_FT_BEING_DELETED', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS BEING DELETED TABLE', 'GPL', 'FORCE'],
        'INNODB_FT_CONFIG' => ['INNODB_FT_CONFIG', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS CONFIG TABLE', 'GPL', 'FORCE'],
        'INNODB_FT_INDEX_CACHE' => ['INNODB_FT_INDEX_CACHE', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS INDEX CACHED', 'GPL', 'FORCE'],
        'INNODB_FT_INDEX_TABLE' => ['INNODB_FT_INDEX_TABLE', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS INDEX TABLE', 'GPL', 'FORCE'],
        'INNODB_TABLES' => ['INNODB_TABLES', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB INNODB_TABLES', 'GPL', 'FORCE'],
        'INNODB_TABLESTATS' => ['INNODB_TABLESTATS', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB INNODB_TABLESTATS', 'GPL', 'FORCE'],
        'INNODB_INDEXES' => ['INNODB_INDEXES', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB INNODB_INDEXES', 'GPL', 'FORCE'],
        'INNODB_TABLESPACES' => ['INNODB_TABLESPACES', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB INNODB_TABLESPACES', 'GPL', 'FORCE'],
        'INNODB_COLUMNS' => ['INNODB_COLUMNS', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB INNODB_COLUMNS', 'GPL', 'FORCE'],
        'INNODB_VIRTUAL' => ['INNODB_VIRTUAL', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB INNODB_VIRTUAL', 'GPL', 'FORCE'],
        'INNODB_CACHED_INDEXES' => ['INNODB_CACHED_INDEXES', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB cached indexes', 'GPL', 'FORCE'],
        'INNODB_SESSION_TEMP_TABLESPACES' => ['INNODB_SESSION_TEMP_TABLESPACES', '8.4', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Session Temporary tablespaces', 'GPL', 'FORCE'],
        'MyISAM' => ['MyISAM', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'Oracle Corporation', 'MyISAM storage engine', 'GPL', 'FORCE'],
        'MRG_MYISAM' => ['MRG_MYISAM', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'Oracle Corporation', 'Collection of identical MyISAM tables', 'GPL', 'FORCE'],
        'PERFORMANCE_SCHEMA' => ['PERFORMANCE_SCHEMA', '0.1', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'Oracle Corporation', 'Performance Schema', 'GPL', 'FORCE'],
        'TempTable' => ['TempTable', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'Oracle Corporation', 'InnoDB temporary storage engine', 'GPL', 'FORCE'],
        'ARCHIVE' => ['ARCHIVE', '3.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'Oracle Corporation', 'Archive storage engine', 'GPL', 'ON'],
        'BLACKHOLE' => ['BLACKHOLE', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'Oracle Corporation', '/dev/null storage engine (anything you write to it disappears)', 'GPL', 'ON'],
        'FEDERATED' => ['FEDERATED', '1.0', 'DISABLED', 'STORAGE ENGINE', '{server}', null, null, 'Oracle Corporation', 'Federated MySQL storage engine', 'GPL', 'OFF'],
        'ndbcluster' => ['ndbcluster', '1.0', 'DISABLED', 'STORAGE ENGINE', '{server}', null, null, 'Oracle Corporation', 'Clustered, fault-tolerant tables', 'GPL', 'OFF'],
        'ndbinfo' => ['ndbinfo', '0.1', 'DISABLED', 'STORAGE ENGINE', '{server}', null, null, 'Oracle Corporation', 'MySQL Cluster system information storage engine', 'GPL', 'OFF'],
        'ndb_transid_mysql_connection_map' => ['ndb_transid_mysql_connection_map', '0.1', 'DISABLED', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Map between MySQL connection ID and NDB transaction ID', 'GPL', 'OFF'],
        'ngram' => ['ngram', '0.1', 'ACTIVE', 'FTPARSER', '1.1', null, null, 'Oracle Corporation', 'Ngram Full-Text Parser', 'GPL', 'ON'],
        'mysqlx_cache_cleaner' => ['mysqlx_cache_cleaner', '1.0', 'ACTIVE', 'AUDIT', '4.1', null, null, 'Oracle Corporation', 'Cache cleaner for sha2 authentication in X plugin', 'GPL', 'ON'],
        'mysqlx' => ['mysqlx', '1.0', 'ACTIVE', 'DAEMON', '{server}', null, null, 'Oracle Corporation', 'X Plugin for MySQL', 'GPL', 'ON'],
        'mysql_native_password' => ['mysql_native_password', '1.1', 'DISABLED', 'AUTHENTICATION', '2.1', null, null, 'Oracle Corporation', 'Native MySQL authentication', 'GPL', 'OFF'],
    ];

    /**
     * @var array<string, array<string, array{string, string, string, string, string, ?string, ?string, ?string, ?string, string, string}>> Release changes to the common definitions
     */
    private const CHANGES = [
        'mysql-5.6.51' => [
            'binlog' => ['binlog', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'MySQL AB', 'This is a pseudo storage engine to represent the binlog in a transaction', 'GPL', 'FORCE'],
            'mysql_native_password' => ['mysql_native_password', '1.0', 'ACTIVE', 'AUTHENTICATION', '1.0', null, null, 'R.J.Silk, Sergei Golubchik', 'Native MySQL authentication', 'GPL', 'FORCE'],
            'mysql_old_password' => ['mysql_old_password', '1.0', 'ACTIVE', 'AUTHENTICATION', '1.0', null, null, 'R.J.Silk, Sergei Golubchik', 'Old MySQL-4.0 authentication', 'GPL', 'FORCE'],
            'sha256_password' => ['sha256_password', '1.0', 'ACTIVE', 'AUTHENTICATION', '1.0', null, null, 'Oracle', 'SHA256 password authentication', 'GPL', 'FORCE'],
            'CSV' => ['CSV', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'Brian Aker, MySQL AB', 'CSV storage engine', 'GPL', 'FORCE'],
            'MEMORY' => ['MEMORY', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'MySQL AB', 'Hash based, stored in memory, useful for temporary tables', 'GPL', 'FORCE'],
            'MyISAM' => ['MyISAM', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'MySQL AB', 'MyISAM storage engine', 'GPL', 'FORCE'],
            'MRG_MYISAM' => ['MRG_MYISAM', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'MySQL AB', 'Collection of identical MyISAM tables', 'GPL', 'FORCE'],
            'ARCHIVE' => ['ARCHIVE', '3.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'Brian Aker, MySQL AB', 'Archive storage engine', 'GPL', 'ON'],
            'BLACKHOLE' => ['BLACKHOLE', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'MySQL AB', '/dev/null storage engine (anything you write to it disappears)', 'GPL', 'ON'],
            'FEDERATED' => ['FEDERATED', '1.0', 'DISABLED', 'STORAGE ENGINE', '{server}', null, null, 'Patrick Galbraith and Brian Aker, MySQL AB', 'Federated MySQL storage engine', 'GPL', 'OFF'],
            'InnoDB' => ['InnoDB', '{series}', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'Oracle Corporation', 'Supports transactions, row-level locking, and foreign keys', 'GPL', 'ON'],
            'INNODB_TRX' => ['INNODB_TRX', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB transactions', 'GPL', 'ON'],
            'INNODB_LOCKS' => ['INNODB_LOCKS', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB conflicting locks', 'GPL', 'ON'],
            'INNODB_LOCK_WAITS' => ['INNODB_LOCK_WAITS', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB which lock is blocking which', 'GPL', 'ON'],
            'INNODB_CMP' => ['INNODB_CMP', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression', 'GPL', 'ON'],
            'INNODB_CMP_RESET' => ['INNODB_CMP_RESET', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression; reset cumulated counts', 'GPL', 'ON'],
            'INNODB_CMPMEM' => ['INNODB_CMPMEM', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compressed buffer pool', 'GPL', 'ON'],
            'INNODB_CMPMEM_RESET' => ['INNODB_CMPMEM_RESET', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compressed buffer pool; reset cumulated counts', 'GPL', 'ON'],
            'INNODB_CMP_PER_INDEX' => ['INNODB_CMP_PER_INDEX', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression (per index)', 'GPL', 'ON'],
            'INNODB_CMP_PER_INDEX_RESET' => ['INNODB_CMP_PER_INDEX_RESET', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression (per index); reset cumulated counts', 'GPL', 'ON'],
            'INNODB_BUFFER_PAGE' => ['INNODB_BUFFER_PAGE', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Buffer Page Information', 'GPL', 'ON'],
            'INNODB_BUFFER_PAGE_LRU' => ['INNODB_BUFFER_PAGE_LRU', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Buffer Page in LRU', 'GPL', 'ON'],
            'INNODB_BUFFER_POOL_STATS' => ['INNODB_BUFFER_POOL_STATS', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Buffer Pool Statistics Information ', 'GPL', 'ON'],
            'INNODB_METRICS' => ['INNODB_METRICS', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Metrics Info', 'GPL', 'ON'],
            'INNODB_FT_DEFAULT_STOPWORD' => ['INNODB_FT_DEFAULT_STOPWORD', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Default stopword list for InnDB Full Text Search', 'GPL', 'ON'],
            'INNODB_FT_DELETED' => ['INNODB_FT_DELETED', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS DELETED TABLE', 'GPL', 'ON'],
            'INNODB_FT_BEING_DELETED' => ['INNODB_FT_BEING_DELETED', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS BEING DELETED TABLE', 'GPL', 'ON'],
            'INNODB_FT_CONFIG' => ['INNODB_FT_CONFIG', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS CONFIG TABLE', 'GPL', 'ON'],
            'INNODB_FT_INDEX_CACHE' => ['INNODB_FT_INDEX_CACHE', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS INDEX CACHED', 'GPL', 'ON'],
            'INNODB_FT_INDEX_TABLE' => ['INNODB_FT_INDEX_TABLE', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS INDEX TABLE', 'GPL', 'ON'],
            'INNODB_SYS_TABLES' => ['INNODB_SYS_TABLES', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB SYS_TABLES', 'GPL', 'ON'],
            'INNODB_SYS_TABLESTATS' => ['INNODB_SYS_TABLESTATS', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB SYS_TABLESTATS', 'GPL', 'ON'],
            'INNODB_SYS_INDEXES' => ['INNODB_SYS_INDEXES', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB SYS_INDEXES', 'GPL', 'ON'],
            'INNODB_SYS_COLUMNS' => ['INNODB_SYS_COLUMNS', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB SYS_COLUMNS', 'GPL', 'ON'],
            'INNODB_SYS_FIELDS' => ['INNODB_SYS_FIELDS', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB SYS_FIELDS', 'GPL', 'ON'],
            'INNODB_SYS_FOREIGN' => ['INNODB_SYS_FOREIGN', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB SYS_FOREIGN', 'GPL', 'ON'],
            'INNODB_SYS_FOREIGN_COLS' => ['INNODB_SYS_FOREIGN_COLS', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB SYS_FOREIGN_COLS', 'GPL', 'ON'],
            'INNODB_SYS_TABLESPACES' => ['INNODB_SYS_TABLESPACES', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB SYS_TABLESPACES', 'GPL', 'ON'],
            'INNODB_SYS_DATAFILES' => ['INNODB_SYS_DATAFILES', '5.6', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB SYS_DATAFILES', 'GPL', 'ON'],
            'PERFORMANCE_SCHEMA' => ['PERFORMANCE_SCHEMA', '0.1', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'Marc Alff, Oracle', 'Performance Schema', 'GPL', 'FORCE'],
            'partition' => ['partition', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'Mikael Ronstrom, MySQL AB', 'Partition Storage Engine Helper', 'GPL', 'ON'],
        ],
        'mysql-5.7.44' => [
            'binlog' => ['binlog', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'MySQL AB', 'This is a pseudo storage engine to represent the binlog in a transaction', 'GPL', 'FORCE'],
            'mysql_native_password' => ['mysql_native_password', '1.1', 'ACTIVE', 'AUTHENTICATION', '1.1', null, null, 'R.J.Silk, Sergei Golubchik', 'Native MySQL authentication', 'GPL', 'FORCE'],
            'sha256_password' => ['sha256_password', '1.1', 'ACTIVE', 'AUTHENTICATION', '1.1', null, null, 'Oracle', 'SHA256 password authentication', 'GPL', 'FORCE'],
            'CSV' => ['CSV', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'Brian Aker, MySQL AB', 'CSV storage engine', 'GPL', 'FORCE'],
            'MEMORY' => ['MEMORY', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'MySQL AB', 'Hash based, stored in memory, useful for temporary tables', 'GPL', 'FORCE'],
            'INNODB_TRX' => ['INNODB_TRX', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB transactions', 'GPL', 'FORCE'],
            'INNODB_LOCKS' => ['INNODB_LOCKS', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB conflicting locks', 'GPL', 'FORCE'],
            'INNODB_LOCK_WAITS' => ['INNODB_LOCK_WAITS', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB which lock is blocking which', 'GPL', 'FORCE'],
            'INNODB_CMP' => ['INNODB_CMP', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression', 'GPL', 'FORCE'],
            'INNODB_CMP_RESET' => ['INNODB_CMP_RESET', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression; reset cumulated counts', 'GPL', 'FORCE'],
            'INNODB_CMPMEM' => ['INNODB_CMPMEM', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compressed buffer pool', 'GPL', 'FORCE'],
            'INNODB_CMPMEM_RESET' => ['INNODB_CMPMEM_RESET', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compressed buffer pool; reset cumulated counts', 'GPL', 'FORCE'],
            'INNODB_CMP_PER_INDEX' => ['INNODB_CMP_PER_INDEX', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression (per index)', 'GPL', 'FORCE'],
            'INNODB_CMP_PER_INDEX_RESET' => ['INNODB_CMP_PER_INDEX_RESET', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression (per index); reset cumulated counts', 'GPL', 'FORCE'],
            'INNODB_BUFFER_PAGE' => ['INNODB_BUFFER_PAGE', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Buffer Page Information', 'GPL', 'FORCE'],
            'INNODB_BUFFER_PAGE_LRU' => ['INNODB_BUFFER_PAGE_LRU', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Buffer Page in LRU', 'GPL', 'FORCE'],
            'INNODB_BUFFER_POOL_STATS' => ['INNODB_BUFFER_POOL_STATS', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Buffer Pool Statistics Information ', 'GPL', 'FORCE'],
            'INNODB_TEMP_TABLE_INFO' => ['INNODB_TEMP_TABLE_INFO', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Temp Table Stats', 'GPL', 'FORCE'],
            'INNODB_METRICS' => ['INNODB_METRICS', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Metrics Info', 'GPL', 'FORCE'],
            'INNODB_FT_DEFAULT_STOPWORD' => ['INNODB_FT_DEFAULT_STOPWORD', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Default stopword list for InnDB Full Text Search', 'GPL', 'FORCE'],
            'INNODB_FT_DELETED' => ['INNODB_FT_DELETED', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS DELETED TABLE', 'GPL', 'FORCE'],
            'INNODB_FT_BEING_DELETED' => ['INNODB_FT_BEING_DELETED', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS BEING DELETED TABLE', 'GPL', 'FORCE'],
            'INNODB_FT_CONFIG' => ['INNODB_FT_CONFIG', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS CONFIG TABLE', 'GPL', 'FORCE'],
            'INNODB_FT_INDEX_CACHE' => ['INNODB_FT_INDEX_CACHE', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS INDEX CACHED', 'GPL', 'FORCE'],
            'INNODB_FT_INDEX_TABLE' => ['INNODB_FT_INDEX_TABLE', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS INDEX TABLE', 'GPL', 'FORCE'],
            'INNODB_SYS_TABLES' => ['INNODB_SYS_TABLES', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB SYS_TABLES', 'GPL', 'FORCE'],
            'INNODB_SYS_TABLESTATS' => ['INNODB_SYS_TABLESTATS', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB SYS_TABLESTATS', 'GPL', 'FORCE'],
            'INNODB_SYS_INDEXES' => ['INNODB_SYS_INDEXES', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB SYS_INDEXES', 'GPL', 'FORCE'],
            'INNODB_SYS_COLUMNS' => ['INNODB_SYS_COLUMNS', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB SYS_COLUMNS', 'GPL', 'FORCE'],
            'INNODB_SYS_FIELDS' => ['INNODB_SYS_FIELDS', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB SYS_FIELDS', 'GPL', 'FORCE'],
            'INNODB_SYS_FOREIGN' => ['INNODB_SYS_FOREIGN', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB SYS_FOREIGN', 'GPL', 'FORCE'],
            'INNODB_SYS_FOREIGN_COLS' => ['INNODB_SYS_FOREIGN_COLS', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB SYS_FOREIGN_COLS', 'GPL', 'FORCE'],
            'INNODB_SYS_TABLESPACES' => ['INNODB_SYS_TABLESPACES', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB SYS_TABLESPACES', 'GPL', 'FORCE'],
            'INNODB_SYS_DATAFILES' => ['INNODB_SYS_DATAFILES', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB SYS_DATAFILES', 'GPL', 'FORCE'],
            'INNODB_SYS_VIRTUAL' => ['INNODB_SYS_VIRTUAL', '5.7', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB SYS_VIRTUAL', 'GPL', 'FORCE'],
            'MyISAM' => ['MyISAM', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'MySQL AB', 'MyISAM storage engine', 'GPL', 'FORCE'],
            'MRG_MYISAM' => ['MRG_MYISAM', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'MySQL AB', 'Collection of identical MyISAM tables', 'GPL', 'FORCE'],
            'PERFORMANCE_SCHEMA' => ['PERFORMANCE_SCHEMA', '0.1', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'Marc Alff, Oracle', 'Performance Schema', 'GPL', 'FORCE'],
            'ARCHIVE' => ['ARCHIVE', '3.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'Brian Aker, MySQL AB', 'Archive storage engine', 'GPL', 'ON'],
            'BLACKHOLE' => ['BLACKHOLE', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'MySQL AB', '/dev/null storage engine (anything you write to it disappears)', 'GPL', 'ON'],
            'FEDERATED' => ['FEDERATED', '1.0', 'DISABLED', 'STORAGE ENGINE', '{server}', null, null, 'Patrick Galbraith and Brian Aker, MySQL AB', 'Federated MySQL storage engine', 'GPL', 'OFF'],
            'partition' => ['partition', '1.0', 'ACTIVE', 'STORAGE ENGINE', '{server}', null, null, 'Mikael Ronstrom, MySQL AB', 'Partition Storage Engine Helper', 'GPL', 'ON'],
            'ngram' => ['ngram', '0.1', 'ACTIVE', 'FTPARSER', '1.1', null, null, 'Oracle Corp', 'Ngram Full-Text Parser', 'GPL', 'ON'],
        ],
        'mysql-8.0.44' => [
            'mysql_native_password' => ['mysql_native_password', '1.1', 'ACTIVE', 'AUTHENTICATION', '2.1', null, null, 'Oracle Corporation', 'Native MySQL authentication', 'GPL', 'FORCE'],
            'INNODB_SESSION_TEMP_TABLESPACES' => ['INNODB_SESSION_TEMP_TABLESPACES', '8.0', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Session Temporary tablespaces', 'GPL', 'FORCE'],
        ],
        'mysql-8.1.0' => [
            'mysql_native_password' => ['mysql_native_password', '1.1', 'ACTIVE', 'AUTHENTICATION', '2.1', null, null, 'Oracle Corporation', 'Native MySQL authentication', 'GPL', 'FORCE'],
            'INNODB_SESSION_TEMP_TABLESPACES' => ['INNODB_SESSION_TEMP_TABLESPACES', '8.1', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Session Temporary tablespaces', 'GPL', 'FORCE'],
        ],
        'mysql-8.2.0' => [
            'INNODB_SESSION_TEMP_TABLESPACES' => ['INNODB_SESSION_TEMP_TABLESPACES', '8.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Session Temporary tablespaces', 'GPL', 'FORCE'],
            'mysql_native_password' => ['mysql_native_password', '1.1', 'ACTIVE', 'AUTHENTICATION', '2.1', null, null, 'Oracle Corporation', 'Native MySQL authentication', 'GPL', 'ON'],
        ],
        'mysql-8.3.0' => [
            'INNODB_SESSION_TEMP_TABLESPACES' => ['INNODB_SESSION_TEMP_TABLESPACES', '8.3', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Session Temporary tablespaces', 'GPL', 'FORCE'],
            'mysql_native_password' => ['mysql_native_password', '1.1', 'ACTIVE', 'AUTHENTICATION', '2.1', null, null, 'Oracle Corporation', 'Native MySQL authentication', 'GPL', 'ON'],
        ],
        'mysql-9.0.1' => [
            'INNODB_TRX' => ['INNODB_TRX', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB transactions', 'GPL', 'FORCE'],
            'INNODB_CMP' => ['INNODB_CMP', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression', 'GPL', 'FORCE'],
            'INNODB_CMP_RESET' => ['INNODB_CMP_RESET', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression; reset cumulated counts', 'GPL', 'FORCE'],
            'INNODB_CMPMEM' => ['INNODB_CMPMEM', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compressed buffer pool', 'GPL', 'FORCE'],
            'INNODB_CMPMEM_RESET' => ['INNODB_CMPMEM_RESET', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compressed buffer pool; reset cumulated counts', 'GPL', 'FORCE'],
            'INNODB_CMP_PER_INDEX' => ['INNODB_CMP_PER_INDEX', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression (per index)', 'GPL', 'FORCE'],
            'INNODB_CMP_PER_INDEX_RESET' => ['INNODB_CMP_PER_INDEX_RESET', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression (per index); reset cumulated counts', 'GPL', 'FORCE'],
            'INNODB_BUFFER_PAGE' => ['INNODB_BUFFER_PAGE', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Buffer Page Information', 'GPL', 'FORCE'],
            'INNODB_BUFFER_PAGE_LRU' => ['INNODB_BUFFER_PAGE_LRU', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Buffer Page in LRU', 'GPL', 'FORCE'],
            'INNODB_BUFFER_POOL_STATS' => ['INNODB_BUFFER_POOL_STATS', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Buffer Pool Statistics Information ', 'GPL', 'FORCE'],
            'INNODB_TEMP_TABLE_INFO' => ['INNODB_TEMP_TABLE_INFO', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Temp Table Stats', 'GPL', 'FORCE'],
            'INNODB_METRICS' => ['INNODB_METRICS', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Metrics Info', 'GPL', 'FORCE'],
            'INNODB_FT_DEFAULT_STOPWORD' => ['INNODB_FT_DEFAULT_STOPWORD', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Default stopword list for InnDB Full Text Search', 'GPL', 'FORCE'],
            'INNODB_FT_DELETED' => ['INNODB_FT_DELETED', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS DELETED TABLE', 'GPL', 'FORCE'],
            'INNODB_FT_BEING_DELETED' => ['INNODB_FT_BEING_DELETED', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS BEING DELETED TABLE', 'GPL', 'FORCE'],
            'INNODB_FT_CONFIG' => ['INNODB_FT_CONFIG', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS CONFIG TABLE', 'GPL', 'FORCE'],
            'INNODB_FT_INDEX_CACHE' => ['INNODB_FT_INDEX_CACHE', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS INDEX CACHED', 'GPL', 'FORCE'],
            'INNODB_FT_INDEX_TABLE' => ['INNODB_FT_INDEX_TABLE', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS INDEX TABLE', 'GPL', 'FORCE'],
            'INNODB_TABLES' => ['INNODB_TABLES', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB INNODB_TABLES', 'GPL', 'FORCE'],
            'INNODB_TABLESTATS' => ['INNODB_TABLESTATS', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB INNODB_TABLESTATS', 'GPL', 'FORCE'],
            'INNODB_INDEXES' => ['INNODB_INDEXES', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB INNODB_INDEXES', 'GPL', 'FORCE'],
            'INNODB_TABLESPACES' => ['INNODB_TABLESPACES', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB INNODB_TABLESPACES', 'GPL', 'FORCE'],
            'INNODB_COLUMNS' => ['INNODB_COLUMNS', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB INNODB_COLUMNS', 'GPL', 'FORCE'],
            'INNODB_VIRTUAL' => ['INNODB_VIRTUAL', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB INNODB_VIRTUAL', 'GPL', 'FORCE'],
            'INNODB_CACHED_INDEXES' => ['INNODB_CACHED_INDEXES', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB cached indexes', 'GPL', 'FORCE'],
            'INNODB_SESSION_TEMP_TABLESPACES' => ['INNODB_SESSION_TEMP_TABLESPACES', '9.0', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Session Temporary tablespaces', 'GPL', 'FORCE'],
        ],
        'mysql-9.1.0' => [
            'INNODB_TRX' => ['INNODB_TRX', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB transactions', 'GPL', 'FORCE'],
            'INNODB_CMP' => ['INNODB_CMP', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression', 'GPL', 'FORCE'],
            'INNODB_CMP_RESET' => ['INNODB_CMP_RESET', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression; reset cumulated counts', 'GPL', 'FORCE'],
            'INNODB_CMPMEM' => ['INNODB_CMPMEM', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compressed buffer pool', 'GPL', 'FORCE'],
            'INNODB_CMPMEM_RESET' => ['INNODB_CMPMEM_RESET', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compressed buffer pool; reset cumulated counts', 'GPL', 'FORCE'],
            'INNODB_CMP_PER_INDEX' => ['INNODB_CMP_PER_INDEX', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression (per index)', 'GPL', 'FORCE'],
            'INNODB_CMP_PER_INDEX_RESET' => ['INNODB_CMP_PER_INDEX_RESET', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Statistics for the InnoDB compression (per index); reset cumulated counts', 'GPL', 'FORCE'],
            'INNODB_BUFFER_PAGE' => ['INNODB_BUFFER_PAGE', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Buffer Page Information', 'GPL', 'FORCE'],
            'INNODB_BUFFER_PAGE_LRU' => ['INNODB_BUFFER_PAGE_LRU', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Buffer Page in LRU', 'GPL', 'FORCE'],
            'INNODB_BUFFER_POOL_STATS' => ['INNODB_BUFFER_POOL_STATS', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Buffer Pool Statistics Information ', 'GPL', 'FORCE'],
            'INNODB_TEMP_TABLE_INFO' => ['INNODB_TEMP_TABLE_INFO', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Temp Table Stats', 'GPL', 'FORCE'],
            'INNODB_METRICS' => ['INNODB_METRICS', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Metrics Info', 'GPL', 'FORCE'],
            'INNODB_FT_DEFAULT_STOPWORD' => ['INNODB_FT_DEFAULT_STOPWORD', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'Default stopword list for InnDB Full Text Search', 'GPL', 'FORCE'],
            'INNODB_FT_DELETED' => ['INNODB_FT_DELETED', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS DELETED TABLE', 'GPL', 'FORCE'],
            'INNODB_FT_BEING_DELETED' => ['INNODB_FT_BEING_DELETED', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS BEING DELETED TABLE', 'GPL', 'FORCE'],
            'INNODB_FT_CONFIG' => ['INNODB_FT_CONFIG', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS CONFIG TABLE', 'GPL', 'FORCE'],
            'INNODB_FT_INDEX_CACHE' => ['INNODB_FT_INDEX_CACHE', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS INDEX CACHED', 'GPL', 'FORCE'],
            'INNODB_FT_INDEX_TABLE' => ['INNODB_FT_INDEX_TABLE', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'INNODB AUXILIARY FTS INDEX TABLE', 'GPL', 'FORCE'],
            'INNODB_TABLES' => ['INNODB_TABLES', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB INNODB_TABLES', 'GPL', 'FORCE'],
            'INNODB_TABLESTATS' => ['INNODB_TABLESTATS', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB INNODB_TABLESTATS', 'GPL', 'FORCE'],
            'INNODB_INDEXES' => ['INNODB_INDEXES', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB INNODB_INDEXES', 'GPL', 'FORCE'],
            'INNODB_TABLESPACES' => ['INNODB_TABLESPACES', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB INNODB_TABLESPACES', 'GPL', 'FORCE'],
            'INNODB_COLUMNS' => ['INNODB_COLUMNS', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB INNODB_COLUMNS', 'GPL', 'FORCE'],
            'INNODB_VIRTUAL' => ['INNODB_VIRTUAL', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB INNODB_VIRTUAL', 'GPL', 'FORCE'],
            'INNODB_CACHED_INDEXES' => ['INNODB_CACHED_INDEXES', '9.2', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB cached indexes', 'GPL', 'FORCE'],
            'INNODB_SESSION_TEMP_TABLESPACES' => ['INNODB_SESSION_TEMP_TABLESPACES', '9.1', 'ACTIVE', 'INFORMATION SCHEMA', '{server}', null, null, 'Oracle Corporation', 'InnoDB Session Temporary tablespaces', 'GPL', 'FORCE'],
        ],
    ];

    /**
     * @var array<string, list<string>> Available definitions in server order, for every supported release
     */
    private const RELEASES = [
        'mysql-5.6.51' => ['binlog', 'mysql_native_password', 'mysql_old_password', 'sha256_password', 'CSV', 'MEMORY', 'MyISAM', 'MRG_MYISAM', 'ARCHIVE', 'BLACKHOLE', 'FEDERATED', 'InnoDB', 'INNODB_TRX', 'INNODB_LOCKS', 'INNODB_LOCK_WAITS', 'INNODB_CMP', 'INNODB_CMP_RESET', 'INNODB_CMPMEM', 'INNODB_CMPMEM_RESET', 'INNODB_CMP_PER_INDEX', 'INNODB_CMP_PER_INDEX_RESET', 'INNODB_BUFFER_PAGE', 'INNODB_BUFFER_PAGE_LRU', 'INNODB_BUFFER_POOL_STATS', 'INNODB_METRICS', 'INNODB_FT_DEFAULT_STOPWORD', 'INNODB_FT_DELETED', 'INNODB_FT_BEING_DELETED', 'INNODB_FT_CONFIG', 'INNODB_FT_INDEX_CACHE', 'INNODB_FT_INDEX_TABLE', 'INNODB_SYS_TABLES', 'INNODB_SYS_TABLESTATS', 'INNODB_SYS_INDEXES', 'INNODB_SYS_COLUMNS', 'INNODB_SYS_FIELDS', 'INNODB_SYS_FOREIGN', 'INNODB_SYS_FOREIGN_COLS', 'INNODB_SYS_TABLESPACES', 'INNODB_SYS_DATAFILES', 'PERFORMANCE_SCHEMA', 'partition'],
        'mysql-5.7.44' => ['binlog', 'mysql_native_password', 'sha256_password', 'CSV', 'MEMORY', 'InnoDB', 'INNODB_TRX', 'INNODB_LOCKS', 'INNODB_LOCK_WAITS', 'INNODB_CMP', 'INNODB_CMP_RESET', 'INNODB_CMPMEM', 'INNODB_CMPMEM_RESET', 'INNODB_CMP_PER_INDEX', 'INNODB_CMP_PER_INDEX_RESET', 'INNODB_BUFFER_PAGE', 'INNODB_BUFFER_PAGE_LRU', 'INNODB_BUFFER_POOL_STATS', 'INNODB_TEMP_TABLE_INFO', 'INNODB_METRICS', 'INNODB_FT_DEFAULT_STOPWORD', 'INNODB_FT_DELETED', 'INNODB_FT_BEING_DELETED', 'INNODB_FT_CONFIG', 'INNODB_FT_INDEX_CACHE', 'INNODB_FT_INDEX_TABLE', 'INNODB_SYS_TABLES', 'INNODB_SYS_TABLESTATS', 'INNODB_SYS_INDEXES', 'INNODB_SYS_COLUMNS', 'INNODB_SYS_FIELDS', 'INNODB_SYS_FOREIGN', 'INNODB_SYS_FOREIGN_COLS', 'INNODB_SYS_TABLESPACES', 'INNODB_SYS_DATAFILES', 'INNODB_SYS_VIRTUAL', 'MyISAM', 'MRG_MYISAM', 'PERFORMANCE_SCHEMA', 'ARCHIVE', 'BLACKHOLE', 'FEDERATED', 'partition', 'ngram'],
        'mysql-8.0.44' => ['binlog', 'mysql_native_password', 'sha256_password', 'caching_sha2_password', 'sha2_cache_cleaner', 'daemon_keyring_proxy_plugin', 'CSV', 'MEMORY', 'InnoDB', 'INNODB_TRX', 'INNODB_CMP', 'INNODB_CMP_RESET', 'INNODB_CMPMEM', 'INNODB_CMPMEM_RESET', 'INNODB_CMP_PER_INDEX', 'INNODB_CMP_PER_INDEX_RESET', 'INNODB_BUFFER_PAGE', 'INNODB_BUFFER_PAGE_LRU', 'INNODB_BUFFER_POOL_STATS', 'INNODB_TEMP_TABLE_INFO', 'INNODB_METRICS', 'INNODB_FT_DEFAULT_STOPWORD', 'INNODB_FT_DELETED', 'INNODB_FT_BEING_DELETED', 'INNODB_FT_CONFIG', 'INNODB_FT_INDEX_CACHE', 'INNODB_FT_INDEX_TABLE', 'INNODB_TABLES', 'INNODB_TABLESTATS', 'INNODB_INDEXES', 'INNODB_TABLESPACES', 'INNODB_COLUMNS', 'INNODB_VIRTUAL', 'INNODB_CACHED_INDEXES', 'INNODB_SESSION_TEMP_TABLESPACES', 'MyISAM', 'MRG_MYISAM', 'PERFORMANCE_SCHEMA', 'TempTable', 'ARCHIVE', 'BLACKHOLE', 'FEDERATED', 'ndbcluster', 'ndbinfo', 'ndb_transid_mysql_connection_map', 'ngram', 'mysqlx_cache_cleaner', 'mysqlx'],
        'mysql-8.1.0' => ['binlog', 'mysql_native_password', 'sha256_password', 'caching_sha2_password', 'sha2_cache_cleaner', 'daemon_keyring_proxy_plugin', 'CSV', 'MEMORY', 'InnoDB', 'INNODB_TRX', 'INNODB_CMP', 'INNODB_CMP_RESET', 'INNODB_CMPMEM', 'INNODB_CMPMEM_RESET', 'INNODB_CMP_PER_INDEX', 'INNODB_CMP_PER_INDEX_RESET', 'INNODB_BUFFER_PAGE', 'INNODB_BUFFER_PAGE_LRU', 'INNODB_BUFFER_POOL_STATS', 'INNODB_TEMP_TABLE_INFO', 'INNODB_METRICS', 'INNODB_FT_DEFAULT_STOPWORD', 'INNODB_FT_DELETED', 'INNODB_FT_BEING_DELETED', 'INNODB_FT_CONFIG', 'INNODB_FT_INDEX_CACHE', 'INNODB_FT_INDEX_TABLE', 'INNODB_TABLES', 'INNODB_TABLESTATS', 'INNODB_INDEXES', 'INNODB_TABLESPACES', 'INNODB_COLUMNS', 'INNODB_VIRTUAL', 'INNODB_CACHED_INDEXES', 'INNODB_SESSION_TEMP_TABLESPACES', 'MyISAM', 'MRG_MYISAM', 'PERFORMANCE_SCHEMA', 'TempTable', 'ARCHIVE', 'BLACKHOLE', 'FEDERATED', 'ndbcluster', 'ndbinfo', 'ndb_transid_mysql_connection_map', 'ngram', 'mysqlx_cache_cleaner', 'mysqlx'],
        'mysql-8.2.0' => ['binlog', 'sha256_password', 'caching_sha2_password', 'sha2_cache_cleaner', 'daemon_keyring_proxy_plugin', 'CSV', 'MEMORY', 'InnoDB', 'INNODB_TRX', 'INNODB_CMP', 'INNODB_CMP_RESET', 'INNODB_CMPMEM', 'INNODB_CMPMEM_RESET', 'INNODB_CMP_PER_INDEX', 'INNODB_CMP_PER_INDEX_RESET', 'INNODB_BUFFER_PAGE', 'INNODB_BUFFER_PAGE_LRU', 'INNODB_BUFFER_POOL_STATS', 'INNODB_TEMP_TABLE_INFO', 'INNODB_METRICS', 'INNODB_FT_DEFAULT_STOPWORD', 'INNODB_FT_DELETED', 'INNODB_FT_BEING_DELETED', 'INNODB_FT_CONFIG', 'INNODB_FT_INDEX_CACHE', 'INNODB_FT_INDEX_TABLE', 'INNODB_TABLES', 'INNODB_TABLESTATS', 'INNODB_INDEXES', 'INNODB_TABLESPACES', 'INNODB_COLUMNS', 'INNODB_VIRTUAL', 'INNODB_CACHED_INDEXES', 'INNODB_SESSION_TEMP_TABLESPACES', 'MyISAM', 'MRG_MYISAM', 'PERFORMANCE_SCHEMA', 'TempTable', 'ARCHIVE', 'BLACKHOLE', 'FEDERATED', 'ndbcluster', 'ndbinfo', 'ndb_transid_mysql_connection_map', 'ngram', 'mysqlx_cache_cleaner', 'mysqlx', 'mysql_native_password'],
        'mysql-8.3.0' => ['binlog', 'sha256_password', 'caching_sha2_password', 'sha2_cache_cleaner', 'daemon_keyring_proxy_plugin', 'CSV', 'MEMORY', 'InnoDB', 'INNODB_TRX', 'INNODB_CMP', 'INNODB_CMP_RESET', 'INNODB_CMPMEM', 'INNODB_CMPMEM_RESET', 'INNODB_CMP_PER_INDEX', 'INNODB_CMP_PER_INDEX_RESET', 'INNODB_BUFFER_PAGE', 'INNODB_BUFFER_PAGE_LRU', 'INNODB_BUFFER_POOL_STATS', 'INNODB_TEMP_TABLE_INFO', 'INNODB_METRICS', 'INNODB_FT_DEFAULT_STOPWORD', 'INNODB_FT_DELETED', 'INNODB_FT_BEING_DELETED', 'INNODB_FT_CONFIG', 'INNODB_FT_INDEX_CACHE', 'INNODB_FT_INDEX_TABLE', 'INNODB_TABLES', 'INNODB_TABLESTATS', 'INNODB_INDEXES', 'INNODB_TABLESPACES', 'INNODB_COLUMNS', 'INNODB_VIRTUAL', 'INNODB_CACHED_INDEXES', 'INNODB_SESSION_TEMP_TABLESPACES', 'MyISAM', 'MRG_MYISAM', 'PERFORMANCE_SCHEMA', 'TempTable', 'ARCHIVE', 'BLACKHOLE', 'FEDERATED', 'ndbcluster', 'ndbinfo', 'ndb_transid_mysql_connection_map', 'ngram', 'mysqlx_cache_cleaner', 'mysqlx', 'mysql_native_password'],
        'mysql-8.4.7' => ['binlog', 'sha256_password', 'caching_sha2_password', 'sha2_cache_cleaner', 'daemon_keyring_proxy_plugin', 'CSV', 'MEMORY', 'InnoDB', 'INNODB_TRX', 'INNODB_CMP', 'INNODB_CMP_RESET', 'INNODB_CMPMEM', 'INNODB_CMPMEM_RESET', 'INNODB_CMP_PER_INDEX', 'INNODB_CMP_PER_INDEX_RESET', 'INNODB_BUFFER_PAGE', 'INNODB_BUFFER_PAGE_LRU', 'INNODB_BUFFER_POOL_STATS', 'INNODB_TEMP_TABLE_INFO', 'INNODB_METRICS', 'INNODB_FT_DEFAULT_STOPWORD', 'INNODB_FT_DELETED', 'INNODB_FT_BEING_DELETED', 'INNODB_FT_CONFIG', 'INNODB_FT_INDEX_CACHE', 'INNODB_FT_INDEX_TABLE', 'INNODB_TABLES', 'INNODB_TABLESTATS', 'INNODB_INDEXES', 'INNODB_TABLESPACES', 'INNODB_COLUMNS', 'INNODB_VIRTUAL', 'INNODB_CACHED_INDEXES', 'INNODB_SESSION_TEMP_TABLESPACES', 'MyISAM', 'MRG_MYISAM', 'PERFORMANCE_SCHEMA', 'TempTable', 'ARCHIVE', 'BLACKHOLE', 'FEDERATED', 'ndbcluster', 'ndbinfo', 'ndb_transid_mysql_connection_map', 'ngram', 'mysqlx_cache_cleaner', 'mysqlx', 'mysql_native_password'],
        'mysql-9.0.1' => ['binlog', 'sha256_password', 'caching_sha2_password', 'sha2_cache_cleaner', 'daemon_keyring_proxy_plugin', 'CSV', 'MEMORY', 'InnoDB', 'INNODB_TRX', 'INNODB_CMP', 'INNODB_CMP_RESET', 'INNODB_CMPMEM', 'INNODB_CMPMEM_RESET', 'INNODB_CMP_PER_INDEX', 'INNODB_CMP_PER_INDEX_RESET', 'INNODB_BUFFER_PAGE', 'INNODB_BUFFER_PAGE_LRU', 'INNODB_BUFFER_POOL_STATS', 'INNODB_TEMP_TABLE_INFO', 'INNODB_METRICS', 'INNODB_FT_DEFAULT_STOPWORD', 'INNODB_FT_DELETED', 'INNODB_FT_BEING_DELETED', 'INNODB_FT_CONFIG', 'INNODB_FT_INDEX_CACHE', 'INNODB_FT_INDEX_TABLE', 'INNODB_TABLES', 'INNODB_TABLESTATS', 'INNODB_INDEXES', 'INNODB_TABLESPACES', 'INNODB_COLUMNS', 'INNODB_VIRTUAL', 'INNODB_CACHED_INDEXES', 'INNODB_SESSION_TEMP_TABLESPACES', 'MyISAM', 'MRG_MYISAM', 'PERFORMANCE_SCHEMA', 'TempTable', 'ARCHIVE', 'BLACKHOLE', 'FEDERATED', 'ndbcluster', 'ndbinfo', 'ndb_transid_mysql_connection_map', 'ngram', 'mysqlx_cache_cleaner', 'mysqlx'],
        'mysql-9.1.0' => ['binlog', 'sha256_password', 'caching_sha2_password', 'sha2_cache_cleaner', 'daemon_keyring_proxy_plugin', 'CSV', 'MEMORY', 'InnoDB', 'INNODB_TRX', 'INNODB_CMP', 'INNODB_CMP_RESET', 'INNODB_CMPMEM', 'INNODB_CMPMEM_RESET', 'INNODB_CMP_PER_INDEX', 'INNODB_CMP_PER_INDEX_RESET', 'INNODB_BUFFER_PAGE', 'INNODB_BUFFER_PAGE_LRU', 'INNODB_BUFFER_POOL_STATS', 'INNODB_TEMP_TABLE_INFO', 'INNODB_METRICS', 'INNODB_FT_DEFAULT_STOPWORD', 'INNODB_FT_DELETED', 'INNODB_FT_BEING_DELETED', 'INNODB_FT_CONFIG', 'INNODB_FT_INDEX_CACHE', 'INNODB_FT_INDEX_TABLE', 'INNODB_TABLES', 'INNODB_TABLESTATS', 'INNODB_INDEXES', 'INNODB_TABLESPACES', 'INNODB_COLUMNS', 'INNODB_VIRTUAL', 'INNODB_CACHED_INDEXES', 'INNODB_SESSION_TEMP_TABLESPACES', 'MyISAM', 'MRG_MYISAM', 'PERFORMANCE_SCHEMA', 'TempTable', 'ARCHIVE', 'BLACKHOLE', 'FEDERATED', 'ndbcluster', 'ndbinfo', 'ndb_transid_mysql_connection_map', 'ngram', 'mysqlx_cache_cleaner', 'mysqlx'],
    ];

    /**
     * @throws RuntimeException When the release or one of its declarations is unavailable
     * @return list<array{string, string, string, string, string, ?string, ?string, ?string, ?string, string, string}>
     */
    public static function of(GrammarRelease $release): array
    {
        $definitions = (self::CHANGES[$release->value] ?? []) + self::COMMON;
        $names = self::RELEASES[$release->value] ?? throw new RuntimeException('A supported MySQL release is required.');
        [$major, $minor, $patch] = array_map(intval(...), explode('.', substr($release->value, 6)));
        $version = ['{server}' => ($major * 10000 + $minor * 100 + $patch) . '.0', '{series}' => $major . '.' . $minor];
        $rows = [];
        foreach ($names as $name) {
            $row = $definitions[$name] ?? throw new RuntimeException('Missing built-in declaration: ' . $name);
            $row[1] = strtr($row[1], $version);
            $row[4] = strtr($row[4], $version);
            $rows[] = $row;
        }
        return $rows;
    }
}
