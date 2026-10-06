<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Noise;

/**
 * The noise and synonym token positions of the productions of replication statements.
 *
 * Only a token with no influence on meaning in its production may be listed
 * as noise, and only terminals the manual defines as synonyms may share a
 * key. Every entry is listed in the method documentation with its reason and
 * the manual page that states it. Nothing else is skipped or merged by the
 * token correspondence check.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ReplicationNoise
{
    /**
     * Answers the noise positions by production signature.
     *
     * - `change_replication_source: REPLICATION SOURCE_SYM` position 0: the two
     *   words REPLICATION SOURCE are one keyword of the statement, keyed by
     *   SOURCE_SYM, so that they compare with their synonym MASTER (see
     *   synonyms()).
     * - `master_or_binary_logs_and_gtids: BINARY_SYM LOGS_SYM AND_SYM GTIDS_SYM`
     *   positions 1 to 3: the four words BINARY LOGS AND GTIDS are one keyword,
     *   keyed by BINARY_SYM, so that they compare with their synonym MASTER.
     * - `ignore_server_id_list: ignore_server_id_list , ignore_server_id`
     *   position 1: the separator of the server identifiers. The list may begin
     *   with an empty element (`IGNORE_SERVER_IDS = ( , 2)`), which adds no
     *   identifier: the server collects the identifiers of the ignore_server_id
     *   items only (sql/sql_yacc.yy, rules ignore_server_id_list and
     *   ignore_server_id; https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html#crs-opt-ignore_server_ids).
     *   The identifiers are kept as a list, so the separators carry nothing more.
     *
     * @return array<string, list<int>>
     */
    public static function positions(): array
    {
        return [
            'change_replication_source: REPLICATION SOURCE_SYM' => [0],
            'master_or_binary_logs_and_gtids: BINARY_SYM LOGS_SYM AND_SYM GTIDS_SYM' => [1, 2, 3],
            'ignore_server_id_list: ignore_server_id_list , ignore_server_id' => [1],
        ];
    }

    /**
     * Answers the key of each synonym position by production signature.
     *
     * - `change_replication_source: MASTER_SYM` and every
     *   `change_replication_source_*` and `source_log_*` alternative spelled
     *   MASTER_ (8.0 to 8.3): CHANGE MASTER TO and its MASTER_ options are the
     *   deprecated aliases of CHANGE REPLICATION SOURCE TO and its SOURCE_
     *   options; the parser fills the same LEX_SOURCE_INFO field and only adds
     *   a deprecation warning (https://dev.mysql.com/doc/refman/8.0/en/change-master-to.html:
     *   "From MySQL 8.0.23, use CHANGE REPLICATION SOURCE TO in place of CHANGE
     *   MASTER TO, which is deprecated from that release"; sql/sql_yacc.yy of
     *   8.0.44, rules change_replication_source_*).
     * - `reset_option: SLAVE opt_replica_reset_options opt_channel` (8.0 to
     *   8.3): RESET SLAVE is the deprecated alias of RESET REPLICA, setting the
     *   same REFRESH_REPLICA flag (https://dev.mysql.com/doc/refman/8.0/en/reset-slave.html,
     *   https://dev.mysql.com/doc/refman/8.0/en/reset-replica.html). The SLAVE
     *   of the rule `replica` is not listed: SHOW SLAVE STATUS and SHOW REPLICA
     *   STATUS name their result columns after it, so START and STOP keep it.
     * - `master_or_binary_logs_and_gtids: MASTER_SYM` (8.2, 8.3): RESET MASTER
     *   is the deprecated form of RESET BINARY LOGS AND GTIDS, setting the same
     *   REFRESH_SOURCE flag (https://dev.mysql.com/doc/relnotes/mysql/8.2/en/news-8-2-0.html:
     *   "MySQL 8.2 deprecates ... RESET MASTER (use RESET BINARY LOGS AND GTIDS
     *   instead)").
     * - `persisted_variable_ident: DEFAULT_SYM . ident` position 0: the server
     *   names the component `default` whether DEFAULT is the keyword or a quoted
     *   identifier (sql/sql_yacc.yy, rule persisted_variable_ident;
     *   https://dev.mysql.com/doc/refman/8.4/en/reset-persist.html).
     *
     * @return array<string, array<int, string>>
     */
    public static function synonyms(): array
    {
        return [
            'change_replication_source: MASTER_SYM' => [0 => 'SOURCE_SYM'],
            'change_replication_source_auto_position: MASTER_AUTO_POSITION_SYM' => [0 => 'SOURCE_AUTO_POSITION_SYM'],
            'change_replication_source_bind: MASTER_BIND_SYM' => [0 => 'SOURCE_BIND_SYM'],
            'change_replication_source_compression_algorithm: MASTER_COMPRESSION_ALGORITHM_SYM' => [0 => 'SOURCE_COMPRESSION_ALGORITHM_SYM'],
            'change_replication_source_connect_retry: MASTER_CONNECT_RETRY_SYM' => [0 => 'SOURCE_CONNECT_RETRY_SYM'],
            'change_replication_source_delay: MASTER_DELAY_SYM' => [0 => 'SOURCE_DELAY_SYM'],
            'change_replication_source_get_source_public_key: GET_MASTER_PUBLIC_KEY_SYM' => [0 => 'GET_SOURCE_PUBLIC_KEY_SYM'],
            'change_replication_source_heartbeat_period: MASTER_HEARTBEAT_PERIOD_SYM' => [0 => 'SOURCE_HEARTBEAT_PERIOD_SYM'],
            'change_replication_source_host: MASTER_HOST_SYM' => [0 => 'SOURCE_HOST_SYM'],
            'change_replication_source_password: MASTER_PASSWORD_SYM' => [0 => 'SOURCE_PASSWORD_SYM'],
            'change_replication_source_port: MASTER_PORT_SYM' => [0 => 'SOURCE_PORT_SYM'],
            'change_replication_source_public_key: MASTER_PUBLIC_KEY_PATH_SYM' => [0 => 'SOURCE_PUBLIC_KEY_PATH_SYM'],
            'change_replication_source_retry_count: MASTER_RETRY_COUNT_SYM' => [0 => 'SOURCE_RETRY_COUNT_SYM'],
            'change_replication_source_ssl: MASTER_SSL_SYM' => [0 => 'SOURCE_SSL_SYM'],
            'change_replication_source_ssl_ca: MASTER_SSL_CA_SYM' => [0 => 'SOURCE_SSL_CA_SYM'],
            'change_replication_source_ssl_capath: MASTER_SSL_CAPATH_SYM' => [0 => 'SOURCE_SSL_CAPATH_SYM'],
            'change_replication_source_ssl_cert: MASTER_SSL_CERT_SYM' => [0 => 'SOURCE_SSL_CERT_SYM'],
            'change_replication_source_ssl_cipher: MASTER_SSL_CIPHER_SYM' => [0 => 'SOURCE_SSL_CIPHER_SYM'],
            'change_replication_source_ssl_crl: MASTER_SSL_CRL_SYM' => [0 => 'SOURCE_SSL_CRL_SYM'],
            'change_replication_source_ssl_crlpath: MASTER_SSL_CRLPATH_SYM' => [0 => 'SOURCE_SSL_CRLPATH_SYM'],
            'change_replication_source_ssl_key: MASTER_SSL_KEY_SYM' => [0 => 'SOURCE_SSL_KEY_SYM'],
            'change_replication_source_ssl_verify_server_cert: MASTER_SSL_VERIFY_SERVER_CERT_SYM' => [0 => 'SOURCE_SSL_VERIFY_SERVER_CERT_SYM'],
            'change_replication_source_tls_ciphersuites: MASTER_TLS_CIPHERSUITES_SYM' => [0 => 'SOURCE_TLS_CIPHERSUITES_SYM'],
            'change_replication_source_tls_version: MASTER_TLS_VERSION_SYM' => [0 => 'SOURCE_TLS_VERSION_SYM'],
            'change_replication_source_user: MASTER_USER_SYM' => [0 => 'SOURCE_USER_SYM'],
            'change_replication_source_zstd_compression_level: MASTER_ZSTD_COMPRESSION_LEVEL_SYM' => [0 => 'SOURCE_ZSTD_COMPRESSION_LEVEL_SYM'],
            'source_log_file: MASTER_LOG_FILE_SYM' => [0 => 'SOURCE_LOG_FILE_SYM'],
            'source_log_pos: MASTER_LOG_POS_SYM' => [0 => 'SOURCE_LOG_POS_SYM'],
            'reset_option: SLAVE opt_replica_reset_options opt_channel' => [0 => 'REPLICA_SYM'],
            'master_or_binary_logs_and_gtids: MASTER_SYM' => [0 => 'BINARY_SYM'],
            'persisted_variable_ident: DEFAULT_SYM . ident' => [0 => 'name:default'],
        ];
    }
}
