<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
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
}
