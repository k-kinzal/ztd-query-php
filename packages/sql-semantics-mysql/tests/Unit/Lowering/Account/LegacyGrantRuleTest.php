<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Account\LegacyGrantRule;

#[CoversClass(LegacyGrantRule::class)]
#[Medium]
final class LegacyGrantRuleTest extends TestCase
{
    public function testGrantLowersEveryCommand(): void
    {
        self::assertSame('GRANT EXECUTE ON FUNCTION f TO u', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('grant execute on function f to u')->toString());
        self::assertSame('GRANT PROXY ON a TO b', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('grant proxy on a to b')->toString());
    }

    public function testRevokeLowersEveryCommand(): void
    {
        self::assertSame("REVOKE PROXY ON a FROM b IDENTIFIED BY 'x'", (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("revoke proxy on a from b identified by 'x'")->toString());
        self::assertSame('REVOKE ALL, GRANT OPTION FROM u', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('revoke all privileges, grant option from u')->toString());
    }

    public function testTableConfirmsTheOptionalWord(): void
    {
        self::assertSame('GRANT SELECT ON t TO u', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('grant select on table t to u')->toString());
    }

    public function testUsersLowersBothListKinds(): void
    {
        self::assertSame('REVOKE SELECT ON t FROM u', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('revoke select on t from u')->toString());
    }
}
