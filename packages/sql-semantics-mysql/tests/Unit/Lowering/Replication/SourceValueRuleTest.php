<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Replication\SourceValueRule;

#[CoversClass(SourceValueRule::class)]
#[Medium]
final class SourceValueRuleTest extends TestCase
{
    public function testValueLowersStringsAndNumbers(): void
    {
        self::assertSame("CHANGE REPLICATION SOURCE TO SOURCE_USER = 'u', SOURCE_PORT = x'10'", (new Semantics(Dialect::MySql))->analyze("change replication source to source_user = 'u', source_port = 0x10")->toString());
    }

    public function testServerIdsLowersTheIdentifiers(): void
    {
        self::assertSame('CHANGE REPLICATION SOURCE TO IGNORE_SERVER_IDS = (1, 2, 3)', (new Semantics(Dialect::MySql))->analyze('change replication source to ignore_server_ids = (1, 2, 3)')->toString());
        self::assertSame('CHANGE REPLICATION SOURCE TO IGNORE_SERVER_IDS = (2)', (new Semantics(Dialect::MySql))->analyze('change replication source to ignore_server_ids = ( , 2)')->toString());
    }

    public function testPrivilegeChecksLowersAnAccount(): void
    {
        self::assertSame('CHANGE REPLICATION SOURCE TO PRIVILEGE_CHECKS_USER = u', (new Semantics(Dialect::MySql))->analyze("change replication source to privilege_checks_user = 'u'")->toString());
    }

    public function testPrimaryKeyCheckLowersTheSetting(): void
    {
        self::assertSame('CHANGE REPLICATION SOURCE TO REQUIRE_TABLE_PRIMARY_KEY_CHECK = STREAM', (new Semantics(Dialect::MySql))->analyze('change replication source to require_table_primary_key_check = stream')->toString());
    }

    public function testAnonymousGtidsLowersTheUuid(): void
    {
        $change = (new Semantics(Dialect::MySql))->analyze("change replication source to assign_gtids_to_anonymous_transactions = '3E11FA47-71CA-11E1-9E33-C80AA9429562'");

        self::assertSame("CHANGE REPLICATION SOURCE TO ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS = '3E11FA47-71CA-11E1-9E33-C80AA9429562'", $change->toString());
        self::assertSame([], $change->facts->diagnostics);
    }

    public function testCipherSuitesLowersAString(): void
    {
        self::assertSame("CHANGE REPLICATION SOURCE TO SOURCE_TLS_CIPHERSUITES = 'x'", (new Semantics(Dialect::MySql))->analyze("change replication source to source_tls_ciphersuites = 'x'")->toString());
    }
}
