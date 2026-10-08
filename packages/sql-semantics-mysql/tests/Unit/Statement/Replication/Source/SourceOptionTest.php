<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\ChangeReplicationSource;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOption;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;
use SqlSemantics\Platform\MySql\Statement\Replication\Terminology;

#[CoversClass(SourceOption::class)]
#[Medium]
final class SourceOptionTest extends TestCase
{
    public function testRenderWritesEveryValueKind(): void
    {
        $sql = "change replication source to privilege_checks_user = 'a'@'b', require_table_primary_key_check = generate, assign_gtids_to_anonymous_transactions = local, source_tls_ciphersuites = null, source_heartbeat_period = 1.5";

        self::assertSame(
            'CHANGE REPLICATION SOURCE TO PRIVILEGE_CHECKS_USER = a@b, REQUIRE_TABLE_PRIMARY_KEY_CHECK = GENERATE, ASSIGN_GTIDS_TO_ANONYMOUS_TRANSACTIONS = LOCAL, SOURCE_TLS_CIPHERSUITES = NULL, SOURCE_HEARTBEAT_PERIOD = 1.5',
            (new Semantics(Dialect::MySql))->analyze($sql)->toString(),
        );
    }

    public function testValueRejectsAValueOutsideTheDomain(): void
    {
        $this->expectExceptionMessage('The value of SOURCE_HOST is outside the domain of the option.');

        new SourceOption(Terminology::Current, SourceOptionKind::Host, new Numeral('1'));
    }

    public function testRenderWritesTheSpellingOfTheSynonym(): void
    {
        $change = (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze("change replication source to master_host = 'h', source_port = 1, get_master_public_key = 1");

        self::assertSame("CHANGE REPLICATION SOURCE TO MASTER_HOST = 'h', SOURCE_PORT = 1, GET_MASTER_PUBLIC_KEY = 1", $change->toString());
        self::assertInstanceOf(ChangeReplicationSource::class, $change->statement);
        self::assertSame([true, false, true], array_column($change->statement->options, 'synonym'));
    }
}
