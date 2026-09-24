<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Configuration\Replication\Source;

/**
 * The options of CHANGE REPLICATION SOURCE TO (CHANGE MASTER TO), named with the SOURCE vocabulary of MySQL 8.0.23 and later.
 * @visibility public
 * @example Reading the spellings
 *     $option = \SqlSemantics\Model\Configuration\Replication\Source\SourceOption::Host;
 *     [$option->value, $option->legacy(), $option->since()] // => ['SOURCE_HOST', 'MASTER_HOST', 0]
 */
enum SourceOption: string
{
    case Host = 'SOURCE_HOST';
    case NetworkNamespace = 'NETWORK_NAMESPACE';
    case Bind = 'SOURCE_BIND';
    case User = 'SOURCE_USER';
    case Password = 'SOURCE_PASSWORD';
    case Port = 'SOURCE_PORT';
    case ConnectRetry = 'SOURCE_CONNECT_RETRY';
    case RetryCount = 'SOURCE_RETRY_COUNT';
    case Delay = 'SOURCE_DELAY';
    case Ssl = 'SOURCE_SSL';
    case SslCa = 'SOURCE_SSL_CA';
    case SslCapath = 'SOURCE_SSL_CAPATH';
    case TlsVersion = 'SOURCE_TLS_VERSION';
    case TlsCiphersuites = 'SOURCE_TLS_CIPHERSUITES';
    case SslCert = 'SOURCE_SSL_CERT';
    case SslCipher = 'SOURCE_SSL_CIPHER';
    case SslKey = 'SOURCE_SSL_KEY';
    case SslVerifyServerCert = 'SOURCE_SSL_VERIFY_SERVER_CERT';
    case SslCrl = 'SOURCE_SSL_CRL';
    case SslCrlpath = 'SOURCE_SSL_CRLPATH';
    case PublicKeyPath = 'SOURCE_PUBLIC_KEY_PATH';
    case GetPublicKey = 'GET_SOURCE_PUBLIC_KEY';
    case HeartbeatPeriod = 'SOURCE_HEARTBEAT_PERIOD';
    case IgnoreServerIds = 'IGNORE_SERVER_IDS';
    case CompressionAlgorithms = 'SOURCE_COMPRESSION_ALGORITHMS';
    case ZstdCompressionLevel = 'SOURCE_ZSTD_COMPRESSION_LEVEL';
    case AutoPosition = 'SOURCE_AUTO_POSITION';
    case PrivilegeChecksUser = 'PRIVILEGE_CHECKS_USER';
    case RequireRowFormat = 'REQUIRE_ROW_FORMAT';
    case RequireTablePrimaryKeyCheck = 'REQUIRE_TABLE_PRIMARY_KEY_CHECK';
    case ConnectionAutoFailover = 'SOURCE_CONNECTION_AUTO_FAILOVER';
    case AssignGtidsToAnonymousTransactions = 'ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS';
    case GtidOnly = 'GTID_ONLY';
    case LogFile = 'SOURCE_LOG_FILE';
    case LogPosition = 'SOURCE_LOG_POS';
    case RelayLogFile = 'RELAY_LOG_FILE';
    case RelayLogPosition = 'RELAY_LOG_POS';

    /**
     * Options whose value is a quoted string.
     */
    public const TEXTS = [
        'SOURCE_HOST', 'NETWORK_NAMESPACE', 'SOURCE_BIND', 'SOURCE_USER', 'SOURCE_PASSWORD', 'SOURCE_SSL_CA', 'SOURCE_SSL_CAPATH', 'SOURCE_TLS_VERSION', 'SOURCE_TLS_CIPHERSUITES',
        'SOURCE_SSL_CERT', 'SOURCE_SSL_CIPHER', 'SOURCE_SSL_KEY', 'SOURCE_SSL_CRL', 'SOURCE_SSL_CRLPATH', 'SOURCE_PUBLIC_KEY_PATH', 'SOURCE_COMPRESSION_ALGORITHMS', 'SOURCE_LOG_FILE', 'RELAY_LOG_FILE',
    ];

    /**
     * Options whose value is an unsigned number.
     */
    public const NUMBERS = ['SOURCE_PORT', 'SOURCE_CONNECT_RETRY', 'SOURCE_RETRY_COUNT', 'SOURCE_DELAY', 'SOURCE_HEARTBEAT_PERIOD', 'SOURCE_ZSTD_COMPRESSION_LEVEL', 'SOURCE_LOG_POS', 'RELAY_LOG_POS'];

    /**
     * Options that switch a behavior on or off.
     */
    public const FLAGS = ['SOURCE_SSL', 'SOURCE_SSL_VERIFY_SERVER_CERT', 'GET_SOURCE_PUBLIC_KEY', 'SOURCE_AUTO_POSITION', 'REQUIRE_ROW_FORMAT', 'SOURCE_CONNECTION_AUTO_FAILOVER', 'GTID_ONLY'];

    /**
     * Options that first appear after MySQL 5.6, with the release that introduces them.
     */
    public const RELEASES = [
        'SOURCE_TLS_VERSION' => 50700, 'NETWORK_NAMESPACE' => 80000, 'SOURCE_TLS_CIPHERSUITES' => 80000, 'SOURCE_PUBLIC_KEY_PATH' => 80000, 'GET_SOURCE_PUBLIC_KEY' => 80000,
        'SOURCE_COMPRESSION_ALGORITHMS' => 80000, 'SOURCE_ZSTD_COMPRESSION_LEVEL' => 80000, 'PRIVILEGE_CHECKS_USER' => 80000, 'REQUIRE_ROW_FORMAT' => 80000,
        'REQUIRE_TABLE_PRIMARY_KEY_CHECK' => 80000, 'SOURCE_CONNECTION_AUTO_FAILOVER' => 80000, 'ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS' => 80000, 'GTID_ONLY' => 80000,
    ];

    /**
     * Reads an option keyword in either vocabulary; MASTER spells SOURCE.
     */
    public static function spelled(string $keyword): ?self
    {
        return self::tryFrom(str_replace('MASTER', 'SOURCE', strtoupper($keyword)));
    }

    /**
     * Returns the MASTER spelling that MySQL releases before 8.0 require.
     */
    public function legacy(): string
    {
        return str_replace('SOURCE', 'MASTER', $this->value);
    }

    /**
     * Returns the first release number (as MySQL numbers releases) that accepts the option.
     */
    public function since(): int
    {
        return self::RELEASES[$this->value] ?? 0;
    }
}
