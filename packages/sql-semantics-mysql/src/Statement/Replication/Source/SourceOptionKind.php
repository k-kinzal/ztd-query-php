<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Source;

use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;

/**
 * The options of CHANGE REPLICATION SOURCE TO and CHANGE MASTER TO: the fields of the server's LEX_SOURCE_INFO.
 *
 * Each case holds the keyword of the current vocabulary. An option whose
 * name begins with SOURCE_ (or GET_SOURCE_) is written MASTER_ (or
 * GET_MASTER_) in the legacy vocabulary; the other options have one name.
 * The log file and position options also form the UNTIL condition of START
 * REPLICA.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/change-replication-source-to.html,
 * https://dev.mysql.com/doc/refman/5.7/en/change-master-to.html.
 *
 * @visibility public
 * @example Spelling an option in both vocabularies
 *     [\SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::Host->keyword(\SqlSemantics\Platform\MySql\Statement\Replication\Terminology::Legacy), \SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind::Host->value] // => ['MASTER_HOST', 'SOURCE_HOST']
 */
enum SourceOptionKind: string
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
    case SslCert = 'SOURCE_SSL_CERT';
    case SslCipher = 'SOURCE_SSL_CIPHER';
    case SslKey = 'SOURCE_SSL_KEY';
    case SslVerifyServerCert = 'SOURCE_SSL_VERIFY_SERVER_CERT';
    case SslCrl = 'SOURCE_SSL_CRL';
    case SslCrlpath = 'SOURCE_SSL_CRLPATH';
    case TlsVersion = 'SOURCE_TLS_VERSION';
    case TlsCiphersuites = 'SOURCE_TLS_CIPHERSUITES';
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
     * Answers the keyword of the option in a vocabulary.
     */
    public function keyword(Terminology $terminology): string
    {
        return $terminology === Terminology::Legacy && $this->renamed() ? str_replace('SOURCE_', 'MASTER_', $this->value) : $this->value;
    }

    /**
     * Tells whether the legacy vocabulary spells the option with MASTER.
     */
    public function renamed(): bool
    {
        return str_contains($this->value, 'SOURCE_') && $this !== self::ConnectionAutoFailover;
    }

    /**
     * Tells whether a value belongs to the domain of the option.
     *
     * A file, host, path, name or algorithm list is a string; a port, count,
     * switch or level a number; the heartbeat period a number literal that
     * may have a fraction; IGNORE_SERVER_IDS a parenthesized list;
     * PRIVILEGE_CHECKS_USER an account or NULL; SOURCE_TLS_CIPHERSUITES a
     * string or NULL; the other two options their keywords, and for
     * ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS also a UUID string.
     */
    public function accepts(Text|Numeral|NumberLiteral|ServerIds|AccountName|PrimaryKeyCheck|AnonymousGtids|null $value): bool
    {
        return match ($this) {
            self::Host, self::NetworkNamespace, self::Bind, self::User, self::Password, self::SslCa, self::SslCapath, self::SslCert, self::SslCipher,
            self::SslKey, self::SslCrl, self::SslCrlpath, self::TlsVersion, self::PublicKeyPath, self::CompressionAlgorithms, self::LogFile,
            self::RelayLogFile => $value instanceof Text,
            self::Port, self::ConnectRetry, self::RetryCount, self::Delay, self::Ssl, self::SslVerifyServerCert, self::GetPublicKey,
            self::ZstdCompressionLevel, self::AutoPosition, self::RequireRowFormat, self::ConnectionAutoFailover, self::GtidOnly, self::LogPosition,
            self::RelayLogPosition => $value instanceof Numeral,
            self::HeartbeatPeriod => $value instanceof NumberLiteral,
            self::IgnoreServerIds => $value instanceof ServerIds,
            self::PrivilegeChecksUser => $value === null || $value instanceof AccountName,
            self::TlsCiphersuites => $value === null || $value instanceof Text,
            self::RequireTablePrimaryKeyCheck => $value instanceof PrimaryKeyCheck,
            self::AssignGtidsToAnonymousTransactions => $value instanceof AnonymousGtids || $value instanceof Text,
        };
    }

    /**
     * Tells whether the option is part of a log position: a file name or a position in it.
     */
    public function position(): bool
    {
        return $this === self::LogFile || $this === self::LogPosition || $this === self::RelayLogFile || $this === self::RelayLogPosition;
    }
}
