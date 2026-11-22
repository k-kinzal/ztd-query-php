<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Account\LegacyGrantRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(LegacyGrantRule::class)]
#[Medium]
final class LegacyGrantRuleTest extends TestCase
{
    public function testGrantLowersEveryCommand(): void
    {
        self::assertSame('GRANT EXECUTE ON FUNCTION f TO u', (new Inspection('mysql-5.6.51'))->inspect('grant execute on function f to u')->toString());
        self::assertSame('GRANT PROXY ON a TO b', (new Inspection('mysql-5.6.51'))->inspect('grant proxy on a to b')->toString());
    }

    public function testRevokeLowersEveryCommand(): void
    {
        self::assertSame("REVOKE PROXY ON a FROM b IDENTIFIED BY 'x'", (new Inspection('mysql-5.6.51'))->inspect("revoke proxy on a from b identified by 'x'")->toString());
        self::assertSame('REVOKE ALL, GRANT OPTION FROM u', (new Inspection('mysql-5.7.44'))->inspect('revoke all privileges, grant option from u')->toString());
    }

    public function testTableConfirmsTheOptionalWord(): void
    {
        self::assertSame('GRANT SELECT ON t TO u', (new Inspection('mysql-5.7.44'))->inspect('grant select on table t to u')->toString());
    }

    public function testUsersLowersBothListKinds(): void
    {
        self::assertSame('REVOKE SELECT ON t FROM u', (new Inspection('mysql-5.7.44'))->inspect('revoke select on t from u')->toString());
    }
}
