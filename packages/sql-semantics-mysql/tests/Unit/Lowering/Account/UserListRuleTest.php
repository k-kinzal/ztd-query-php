<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Account\UserListRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(UserListRule::class)]
#[Medium]
final class UserListRuleTest extends TestCase
{
    public function testCreatedLowersEveryAccount(): void
    {
        self::assertSame('CREATE USER a, b IDENTIFIED BY RANDOM PASSWORD', (new Inspection())->inspect('create user a, b identified by random password')->toString());
    }

    public function testCreateLowersEveryForm(): void
    {
        self::assertSame('CREATE USER u AND IDENTIFIED WITH p', (new Inspection())->inspect('create user u and identified with p')->toString());
    }

    public function testAlteredLowersEveryAccount(): void
    {
        self::assertSame('ALTER USER a IDENTIFIED WITH p, b IDENTIFIED WITH p BY RANDOM PASSWORD RETAIN CURRENT PASSWORD', (new Inspection())->inspect('alter user a identified with p, b identified with p by random password retain current password')->toString());
    }

    public function testAlterLowersEveryForm(): void
    {
        self::assertSame("ALTER USER u IDENTIFIED WITH p BY 'n' REPLACE 'o' RETAIN CURRENT PASSWORD", (new Inspection())->inspect("alter user u identified with p by 'n' replace 'o' retain current password")->toString());
    }

    public function testFactorsLowersTheFactorChanges(): void
    {
        self::assertSame('ALTER USER u ADD 2 FACTOR IDENTIFIED WITH p ADD 3 FACTOR IDENTIFIED WITH q', (new Inspection())->inspect('alter user u add 2 factor identified with p add 3 factor identified with q')->toString());
    }

    public function testGrantedLowersTheGrantList(): void
    {
        self::assertSame('GRANT SELECT ON *.* TO a, b IDENTIFIED WITH p', (new Inspection('mysql-5.6.51'))->inspect('grant select on *.* to a, b identified with p')->toString());
    }

    public function testGrantLowersEveryForm(): void
    {
        self::assertSame("CREATE USER u IDENTIFIED WITH p BY 'x'", (new Inspection('mysql-5.7.44'))->inspect("create user u identified with p by 'x'")->toString());
    }

    public function testSpineFlattensTheList(): void
    {
        self::assertSame('CREATE USER a, b, c', (new Inspection())->inspect('create user a, b, c')->toString());
    }
}
