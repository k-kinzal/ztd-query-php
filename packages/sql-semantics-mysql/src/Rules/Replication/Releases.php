<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Replication;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;

/**
 * Answers which replication words and options a MySQL release accepts.
 *
 * Rule: MYSQL-REPLICATION-RELEASE-001. The legacy vocabulary is the only one
 * of MySQL 5.6 and 5.7; from 8.0 the current words exist and the legacy
 * spellings of CHANGE MASTER, its MASTER_ options and RESET SLAVE are read
 * as their current synonyms, until 8.4 removes them. RESET MASTER has no
 * current spelling before 8.2 and none of its legacy spelling from 8.4.
 * START and STOP keep their spelling: SLAVE up to 8.3, REPLICA from 8.0.
 * An option exists from the release that introduced it: SOURCE_TLS_VERSION
 * from 5.7, the options of 8.0 from 8.0. Terminates: constant work.
 * Source: https://dev.mysql.com/doc/refman/8.0/en/change-master-to.html,
 * https://dev.mysql.com/doc/refman/8.0/en/start-replica.html,
 * https://dev.mysql.com/doc/relnotes/mysql/8.2/en/news-8-2-0.html,
 * https://dev.mysql.com/doc/refman/5.7/en/change-master-to.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Releases
{
    /**
     * Tells whether a release is the given one or a later one.
     */
    public function since(GrammarRelease $release, GrammarRelease $first): bool
    {
        return array_search($release, GrammarRelease::cases(), true) >= array_search($first, GrammarRelease::cases(), true);
    }

    /**
     * Tells whether a release is one of the 5.x grammars.
     */
    public function legacy(GrammarRelease $release): bool
    {
        return $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;
    }

    /**
     * Answers the vocabulary of CHANGE REPLICATION SOURCE, RESET REPLICA and the log positions of UNTIL in a release.
     */
    public function source(GrammarRelease $release): Terminology
    {
        return $this->legacy($release) ? Terminology::Legacy : Terminology::Current;
    }

    /**
     * Answers the vocabulary of RESET BINARY LOGS AND GTIDS in a release.
     */
    public function binaryLogs(GrammarRelease $release): Terminology
    {
        return $this->since($release, GrammarRelease::MySql820) ? Terminology::Current : Terminology::Legacy;
    }

    /**
     * Tells whether a release accepts START or STOP written in a vocabulary.
     */
    public function replica(GrammarRelease $release, Terminology $terminology): bool
    {
        return $terminology === Terminology::Legacy ? !$this->since($release, GrammarRelease::MySql847) : !$this->legacy($release);
    }

    /**
     * Tells whether a release accepts an option of CHANGE REPLICATION SOURCE.
     */
    public function option(GrammarRelease $release, SourceOptionKind $kind): bool
    {
        return match ($kind) {
            SourceOptionKind::Host, SourceOptionKind::Bind, SourceOptionKind::User, SourceOptionKind::Password, SourceOptionKind::Port,
            SourceOptionKind::ConnectRetry, SourceOptionKind::RetryCount, SourceOptionKind::Delay, SourceOptionKind::Ssl, SourceOptionKind::SslCa,
            SourceOptionKind::SslCapath, SourceOptionKind::SslCert, SourceOptionKind::SslCipher, SourceOptionKind::SslKey,
            SourceOptionKind::SslVerifyServerCert, SourceOptionKind::SslCrl, SourceOptionKind::SslCrlpath, SourceOptionKind::HeartbeatPeriod,
            SourceOptionKind::IgnoreServerIds, SourceOptionKind::AutoPosition, SourceOptionKind::LogFile, SourceOptionKind::LogPosition,
            SourceOptionKind::RelayLogFile, SourceOptionKind::RelayLogPosition => true,
            SourceOptionKind::TlsVersion => $release !== GrammarRelease::MySql5651,
            SourceOptionKind::NetworkNamespace, SourceOptionKind::TlsCiphersuites, SourceOptionKind::PublicKeyPath, SourceOptionKind::GetPublicKey,
            SourceOptionKind::CompressionAlgorithms, SourceOptionKind::ZstdCompressionLevel, SourceOptionKind::PrivilegeChecksUser,
            SourceOptionKind::RequireRowFormat, SourceOptionKind::RequireTablePrimaryKeyCheck, SourceOptionKind::ConnectionAutoFailover,
            SourceOptionKind::AssignGtidsToAnonymousTransactions, SourceOptionKind::GtidOnly => !$this->legacy($release),
        };
    }
}
