<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Lowering\Replication;

use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\ImplementationGap;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;

/**
 * Lowers one option of CHANGE MASTER TO or CHANGE REPLICATION SOURCE TO, or one log position of UNTIL.
 *
 * Rule: MYSQL-SOURCE-OPTION-001. Scope: master_def, master_file_def (5.6,
 * 5.7), source_def, source_file_def, source_log_file, source_log_pos and
 * the change_replication_source_* keyword rules (8.0 and later). Each
 * option production names one field of LEX_SOURCE_INFO (SourceOptionKind);
 * from 8.0 to 8.3 an option keyword is a rule of its own whose MASTER_ and
 * SOURCE_ alternatives are synonyms (the parser fills the same field and
 * adds a deprecation warning for the MASTER_ one), so the option takes the
 * vocabulary of the statement, which is the one of the release. The value is
 * lowered by the leaf rules or by MYSQL-SOURCE-VALUE-001. Constructs:
 * SourceOption. Terminates: unit productions over strict subtrees.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html,
 * https://dev.mysql.com/doc/refman/8.0/en/change-master-to.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql\Lowering\Replication
 */
final class SourceOptionRule
{
    /**
     * The option keyword rules of 8.0 to 8.3, by the option they name.
     */
    private const KEYWORDS = [
        'change_replication_source_auto_position: MASTER_AUTO_POSITION_SYM' => SourceOptionKind::AutoPosition,
        'change_replication_source_auto_position: SOURCE_AUTO_POSITION_SYM' => SourceOptionKind::AutoPosition,
        'change_replication_source_bind: MASTER_BIND_SYM' => SourceOptionKind::Bind,
        'change_replication_source_bind: SOURCE_BIND_SYM' => SourceOptionKind::Bind,
        'change_replication_source_compression_algorithm: MASTER_COMPRESSION_ALGORITHM_SYM' => SourceOptionKind::CompressionAlgorithms,
        'change_replication_source_compression_algorithm: SOURCE_COMPRESSION_ALGORITHM_SYM' => SourceOptionKind::CompressionAlgorithms,
        'change_replication_source_connect_retry: MASTER_CONNECT_RETRY_SYM' => SourceOptionKind::ConnectRetry,
        'change_replication_source_connect_retry: SOURCE_CONNECT_RETRY_SYM' => SourceOptionKind::ConnectRetry,
        'change_replication_source_delay: MASTER_DELAY_SYM' => SourceOptionKind::Delay,
        'change_replication_source_delay: SOURCE_DELAY_SYM' => SourceOptionKind::Delay,
        'change_replication_source_get_source_public_key: GET_MASTER_PUBLIC_KEY_SYM' => SourceOptionKind::GetPublicKey,
        'change_replication_source_get_source_public_key: GET_SOURCE_PUBLIC_KEY_SYM' => SourceOptionKind::GetPublicKey,
        'change_replication_source_heartbeat_period: MASTER_HEARTBEAT_PERIOD_SYM' => SourceOptionKind::HeartbeatPeriod,
        'change_replication_source_heartbeat_period: SOURCE_HEARTBEAT_PERIOD_SYM' => SourceOptionKind::HeartbeatPeriod,
        'change_replication_source_host: MASTER_HOST_SYM' => SourceOptionKind::Host,
        'change_replication_source_host: SOURCE_HOST_SYM' => SourceOptionKind::Host,
        'change_replication_source_password: MASTER_PASSWORD_SYM' => SourceOptionKind::Password,
        'change_replication_source_password: SOURCE_PASSWORD_SYM' => SourceOptionKind::Password,
        'change_replication_source_port: MASTER_PORT_SYM' => SourceOptionKind::Port,
        'change_replication_source_port: SOURCE_PORT_SYM' => SourceOptionKind::Port,
        'change_replication_source_public_key: MASTER_PUBLIC_KEY_PATH_SYM' => SourceOptionKind::PublicKeyPath,
        'change_replication_source_public_key: SOURCE_PUBLIC_KEY_PATH_SYM' => SourceOptionKind::PublicKeyPath,
        'change_replication_source_retry_count: MASTER_RETRY_COUNT_SYM' => SourceOptionKind::RetryCount,
        'change_replication_source_retry_count: SOURCE_RETRY_COUNT_SYM' => SourceOptionKind::RetryCount,
        'change_replication_source_ssl: MASTER_SSL_SYM' => SourceOptionKind::Ssl,
        'change_replication_source_ssl: SOURCE_SSL_SYM' => SourceOptionKind::Ssl,
        'change_replication_source_ssl_ca: MASTER_SSL_CA_SYM' => SourceOptionKind::SslCa,
        'change_replication_source_ssl_ca: SOURCE_SSL_CA_SYM' => SourceOptionKind::SslCa,
        'change_replication_source_ssl_capath: MASTER_SSL_CAPATH_SYM' => SourceOptionKind::SslCapath,
        'change_replication_source_ssl_capath: SOURCE_SSL_CAPATH_SYM' => SourceOptionKind::SslCapath,
        'change_replication_source_ssl_cert: MASTER_SSL_CERT_SYM' => SourceOptionKind::SslCert,
        'change_replication_source_ssl_cert: SOURCE_SSL_CERT_SYM' => SourceOptionKind::SslCert,
        'change_replication_source_ssl_cipher: MASTER_SSL_CIPHER_SYM' => SourceOptionKind::SslCipher,
        'change_replication_source_ssl_cipher: SOURCE_SSL_CIPHER_SYM' => SourceOptionKind::SslCipher,
        'change_replication_source_ssl_crl: MASTER_SSL_CRL_SYM' => SourceOptionKind::SslCrl,
        'change_replication_source_ssl_crl: SOURCE_SSL_CRL_SYM' => SourceOptionKind::SslCrl,
        'change_replication_source_ssl_crlpath: MASTER_SSL_CRLPATH_SYM' => SourceOptionKind::SslCrlpath,
        'change_replication_source_ssl_crlpath: SOURCE_SSL_CRLPATH_SYM' => SourceOptionKind::SslCrlpath,
        'change_replication_source_ssl_key: MASTER_SSL_KEY_SYM' => SourceOptionKind::SslKey,
        'change_replication_source_ssl_key: SOURCE_SSL_KEY_SYM' => SourceOptionKind::SslKey,
        'change_replication_source_ssl_verify_server_cert: MASTER_SSL_VERIFY_SERVER_CERT_SYM' => SourceOptionKind::SslVerifyServerCert,
        'change_replication_source_ssl_verify_server_cert: SOURCE_SSL_VERIFY_SERVER_CERT_SYM' => SourceOptionKind::SslVerifyServerCert,
        'change_replication_source_tls_ciphersuites: MASTER_TLS_CIPHERSUITES_SYM' => SourceOptionKind::TlsCiphersuites,
        'change_replication_source_tls_ciphersuites: SOURCE_TLS_CIPHERSUITES_SYM' => SourceOptionKind::TlsCiphersuites,
        'change_replication_source_tls_version: MASTER_TLS_VERSION_SYM' => SourceOptionKind::TlsVersion,
        'change_replication_source_tls_version: SOURCE_TLS_VERSION_SYM' => SourceOptionKind::TlsVersion,
        'change_replication_source_user: MASTER_USER_SYM' => SourceOptionKind::User,
        'change_replication_source_user: SOURCE_USER_SYM' => SourceOptionKind::User,
        'change_replication_source_zstd_compression_level: MASTER_ZSTD_COMPRESSION_LEVEL_SYM' => SourceOptionKind::ZstdCompressionLevel,
        'change_replication_source_zstd_compression_level: SOURCE_ZSTD_COMPRESSION_LEVEL_SYM' => SourceOptionKind::ZstdCompressionLevel,
        'source_log_file: MASTER_LOG_FILE_SYM' => SourceOptionKind::LogFile,
        'source_log_file: SOURCE_LOG_FILE_SYM' => SourceOptionKind::LogFile,
        'source_log_pos: MASTER_LOG_POS_SYM' => SourceOptionKind::LogPosition,
        'source_log_pos: SOURCE_LOG_POS_SYM' => SourceOptionKind::LogPosition,
    ];

    /**
     * The option productions, by the option they set; null when the option is named by a keyword rule at the first position.
     */
    private const OPTIONS = [
        'master_def: IGNORE_SERVER_IDS_SYM EQ ( ignore_server_id_list )' => SourceOptionKind::IgnoreServerIds,
        'master_def: MASTER_AUTO_POSITION_SYM EQ ulong_num' => SourceOptionKind::AutoPosition,
        'master_def: MASTER_BIND_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::Bind,
        'master_def: MASTER_CONNECT_RETRY_SYM EQ ulong_num' => SourceOptionKind::ConnectRetry,
        'master_def: MASTER_DELAY_SYM EQ ulong_num' => SourceOptionKind::Delay,
        'master_def: MASTER_HEARTBEAT_PERIOD_SYM EQ NUM_literal' => SourceOptionKind::HeartbeatPeriod,
        'master_def: MASTER_HOST_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::Host,
        'master_def: MASTER_PASSWORD_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::Password,
        'master_def: MASTER_PORT_SYM EQ ulong_num' => SourceOptionKind::Port,
        'master_def: MASTER_RETRY_COUNT_SYM EQ ulong_num' => SourceOptionKind::RetryCount,
        'master_def: MASTER_SSL_CAPATH_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::SslCapath,
        'master_def: MASTER_SSL_CA_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::SslCa,
        'master_def: MASTER_SSL_CERT_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::SslCert,
        'master_def: MASTER_SSL_CIPHER_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::SslCipher,
        'master_def: MASTER_SSL_CRLPATH_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::SslCrlpath,
        'master_def: MASTER_SSL_CRL_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::SslCrl,
        'master_def: MASTER_SSL_KEY_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::SslKey,
        'master_def: MASTER_SSL_SYM EQ ulong_num' => SourceOptionKind::Ssl,
        'master_def: MASTER_SSL_VERIFY_SERVER_CERT_SYM EQ ulong_num' => SourceOptionKind::SslVerifyServerCert,
        'master_def: MASTER_TLS_VERSION_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::TlsVersion,
        'master_def: MASTER_USER_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::User,
        'master_file_def: MASTER_LOG_FILE_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::LogFile,
        'master_file_def: MASTER_LOG_POS_SYM EQ ulonglong_num' => SourceOptionKind::LogPosition,
        'master_file_def: RELAY_LOG_FILE_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::RelayLogFile,
        'master_file_def: RELAY_LOG_POS_SYM EQ ulong_num' => SourceOptionKind::RelayLogPosition,
        'source_def: ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS_SYM EQ assign_gtids_to_anonymous_transactions_def' => SourceOptionKind::AssignGtidsToAnonymousTransactions,
        'source_def: GET_SOURCE_PUBLIC_KEY_SYM EQ ulong_num' => SourceOptionKind::GetPublicKey,
        'source_def: GTID_ONLY_SYM EQ real_ulong_num' => SourceOptionKind::GtidOnly,
        'source_def: IGNORE_SERVER_IDS_SYM EQ ( ignore_server_id_list )' => SourceOptionKind::IgnoreServerIds,
        'source_def: NETWORK_NAMESPACE_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::NetworkNamespace,
        'source_def: PRIVILEGE_CHECKS_USER_SYM EQ privilege_check_def' => SourceOptionKind::PrivilegeChecksUser,
        'source_def: REQUIRE_ROW_FORMAT_SYM EQ ulong_num' => SourceOptionKind::RequireRowFormat,
        'source_def: REQUIRE_TABLE_PRIMARY_KEY_CHECK_SYM EQ table_primary_key_check_def' => SourceOptionKind::RequireTablePrimaryKeyCheck,
        'source_def: SOURCE_AUTO_POSITION_SYM EQ ulong_num' => SourceOptionKind::AutoPosition,
        'source_def: SOURCE_BIND_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::Bind,
        'source_def: SOURCE_COMPRESSION_ALGORITHM_SYM EQ TEXT_STRING_sys' => SourceOptionKind::CompressionAlgorithms,
        'source_def: SOURCE_CONNECTION_AUTO_FAILOVER_SYM EQ real_ulong_num' => SourceOptionKind::ConnectionAutoFailover,
        'source_def: SOURCE_CONNECT_RETRY_SYM EQ ulong_num' => SourceOptionKind::ConnectRetry,
        'source_def: SOURCE_DELAY_SYM EQ ulong_num' => SourceOptionKind::Delay,
        'source_def: SOURCE_HEARTBEAT_PERIOD_SYM EQ NUM_literal' => SourceOptionKind::HeartbeatPeriod,
        'source_def: SOURCE_HOST_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::Host,
        'source_def: SOURCE_PASSWORD_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::Password,
        'source_def: SOURCE_PORT_SYM EQ ulong_num' => SourceOptionKind::Port,
        'source_def: SOURCE_PUBLIC_KEY_PATH_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::PublicKeyPath,
        'source_def: SOURCE_RETRY_COUNT_SYM EQ ulong_num' => SourceOptionKind::RetryCount,
        'source_def: SOURCE_SSL_CAPATH_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::SslCapath,
        'source_def: SOURCE_SSL_CA_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::SslCa,
        'source_def: SOURCE_SSL_CERT_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::SslCert,
        'source_def: SOURCE_SSL_CIPHER_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::SslCipher,
        'source_def: SOURCE_SSL_CRLPATH_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::SslCrlpath,
        'source_def: SOURCE_SSL_CRL_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::SslCrl,
        'source_def: SOURCE_SSL_KEY_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::SslKey,
        'source_def: SOURCE_SSL_SYM EQ ulong_num' => SourceOptionKind::Ssl,
        'source_def: SOURCE_SSL_VERIFY_SERVER_CERT_SYM EQ ulong_num' => SourceOptionKind::SslVerifyServerCert,
        'source_def: SOURCE_TLS_CIPHERSUITES_SYM EQ source_tls_ciphersuites_def' => SourceOptionKind::TlsCiphersuites,
        'source_def: SOURCE_TLS_VERSION_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::TlsVersion,
        'source_def: SOURCE_USER_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::User,
        'source_def: SOURCE_ZSTD_COMPRESSION_LEVEL_SYM EQ ulong_num' => SourceOptionKind::ZstdCompressionLevel,
        'source_def: change_replication_source_auto_position EQ ulong_num' => null,
        'source_def: change_replication_source_bind EQ TEXT_STRING_sys_nonewline' => null,
        'source_def: change_replication_source_compression_algorithm EQ TEXT_STRING_sys' => null,
        'source_def: change_replication_source_connect_retry EQ ulong_num' => null,
        'source_def: change_replication_source_delay EQ ulong_num' => null,
        'source_def: change_replication_source_get_source_public_key EQ ulong_num' => null,
        'source_def: change_replication_source_heartbeat_period EQ NUM_literal' => null,
        'source_def: change_replication_source_host EQ TEXT_STRING_sys_nonewline' => null,
        'source_def: change_replication_source_password EQ TEXT_STRING_sys_nonewline' => null,
        'source_def: change_replication_source_port EQ ulong_num' => null,
        'source_def: change_replication_source_public_key EQ TEXT_STRING_sys_nonewline' => null,
        'source_def: change_replication_source_retry_count EQ ulong_num' => null,
        'source_def: change_replication_source_ssl EQ ulong_num' => null,
        'source_def: change_replication_source_ssl_ca EQ TEXT_STRING_sys_nonewline' => null,
        'source_def: change_replication_source_ssl_capath EQ TEXT_STRING_sys_nonewline' => null,
        'source_def: change_replication_source_ssl_cert EQ TEXT_STRING_sys_nonewline' => null,
        'source_def: change_replication_source_ssl_cipher EQ TEXT_STRING_sys_nonewline' => null,
        'source_def: change_replication_source_ssl_crl EQ TEXT_STRING_sys_nonewline' => null,
        'source_def: change_replication_source_ssl_crlpath EQ TEXT_STRING_sys_nonewline' => null,
        'source_def: change_replication_source_ssl_key EQ TEXT_STRING_sys_nonewline' => null,
        'source_def: change_replication_source_ssl_verify_server_cert EQ ulong_num' => null,
        'source_def: change_replication_source_tls_ciphersuites EQ source_tls_ciphersuites_def' => null,
        'source_def: change_replication_source_tls_version EQ TEXT_STRING_sys_nonewline' => null,
        'source_def: change_replication_source_user EQ TEXT_STRING_sys_nonewline' => null,
        'source_def: change_replication_source_zstd_compression_level EQ ulong_num' => null,
        'source_file_def: RELAY_LOG_FILE_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::RelayLogFile,
        'source_file_def: RELAY_LOG_POS_SYM EQ ulong_num' => SourceOptionKind::RelayLogPosition,
        'source_file_def: SOURCE_LOG_FILE_SYM EQ TEXT_STRING_sys_nonewline' => SourceOptionKind::LogFile,
        'source_file_def: SOURCE_LOG_POS_SYM EQ ulonglong_num' => SourceOptionKind::LogPosition,
        'source_file_def: source_log_file EQ TEXT_STRING_sys_nonewline' => null,
        'source_file_def: source_log_pos EQ ulonglong_num' => null,
    ];

    /**
     * The productions that pass an option on to their only child.
     */
    private const FORWARD = ['master_def: master_file_def' => true, 'source_def: source_file_def' => true];

    /**
     * @param Lowering $lowering The lowering this rule belongs to
     */
    public function __construct(private readonly Lowering $lowering)
    {
    }

    /**
     * Lowers one option in the vocabulary of its statement.
     *
     * @throws ImplementationGap When a production has no rule
     */
    public function option(Node $option, Terminology $terminology): SourceOption
    {
        $form = $this->lowering->form($option);
        while (isset(self::FORWARD[$form->signature])) {
            $form = $this->lowering->form($form->node(0));
        }
        if (!array_key_exists($form->signature, self::OPTIONS)) {
            throw ImplementationGap::production($form);
        }
        $kind = self::OPTIONS[$form->signature] ?? $this->keyword($form->node(0));

        return new SourceOption($terminology, $kind, (new SourceValueRule($this->lowering))->value($form));
    }

    /**
     * Answers the option an option keyword rule names.
     *
     * @throws ImplementationGap When the production has no rule
     */
    public function keyword(Node $keyword): SourceOptionKind
    {
        $form = $this->lowering->form($keyword);

        return self::KEYWORDS[$form->signature] ?? throw ImplementationGap::production($form);
    }
}
