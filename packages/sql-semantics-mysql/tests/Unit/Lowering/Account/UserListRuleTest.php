<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Account\UserListRule;

#[CoversClass(UserListRule::class)]
#[Medium]
final class UserListRuleTest extends TestCase
{
    public function testCreatedLowersEveryAccount(): void
    {
        self::assertSame('CREATE USER a, b IDENTIFIED BY RANDOM PASSWORD', (new Semantics(Dialect::MySql))->analyze('create user a, b identified by random password')->toString());
    }

    public function testCreateLowersEveryForm(): void
    {
        self::assertSame('CREATE USER u AND IDENTIFIED WITH p', (new Semantics(Dialect::MySql))->analyze('create user u and identified with p')->toString());
    }

    public function testAlteredLowersEveryAccount(): void
    {
        self::assertSame('ALTER USER a IDENTIFIED WITH p, b IDENTIFIED WITH p BY RANDOM PASSWORD RETAIN CURRENT PASSWORD', (new Semantics(Dialect::MySql))->analyze('alter user a identified with p, b identified with p by random password retain current password')->toString());
    }

    public function testAlterLowersEveryForm(): void
    {
        self::assertSame("ALTER USER u IDENTIFIED WITH p BY 'n' REPLACE 'o' RETAIN CURRENT PASSWORD", (new Semantics(Dialect::MySql))->analyze("alter user u identified with p by 'n' replace 'o' retain current password")->toString());
    }

    public function testFactorsLowersTheFactorChanges(): void
    {
        self::assertSame('ALTER USER u ADD 2 FACTOR IDENTIFIED WITH p ADD 3 FACTOR IDENTIFIED WITH q', (new Semantics(Dialect::MySql))->analyze('alter user u add 2 factor identified with p add 3 factor identified with q')->toString());
    }

    public function testGrantedLowersTheGrantList(): void
    {
        self::assertSame('GRANT SELECT ON *.* TO a, b IDENTIFIED WITH p', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('grant select on *.* to a, b identified with p')->toString());
    }

    public function testGrantLowersEveryForm(): void
    {
        self::assertSame("CREATE USER u IDENTIFIED WITH p BY 'x'", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("create user u identified with p by 'x'")->toString());
    }

    public function testSpineFlattensTheList(): void
    {
        self::assertSame('CREATE USER a, b, c', (new Semantics(Dialect::MySql))->analyze('create user a, b, c')->toString());
    }
}
