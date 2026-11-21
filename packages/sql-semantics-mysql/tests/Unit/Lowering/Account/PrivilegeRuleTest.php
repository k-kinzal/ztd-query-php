<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Account\PrivilegeRule;
use SqlSemantics\Platform\MySql\Semantics;

#[CoversClass(PrivilegeRule::class)]
#[Medium]
final class PrivilegeRuleTest extends TestCase
{
    public function testItemsLowersTheList(): void
    {
        self::assertSame('GRANT SELECT, INSERT (a), CREATE ROLE, DROP ROLE ON *.* TO u', (new Semantics())->inspect('grant select, insert (a), create role, drop role on *.* to u')->toString());
    }

    public function testItemLowersEveryStaticPrivilege(): void
    {
        self::assertSame('GRANT CREATE TABLESPACE, CREATE USER, EVENT, TRIGGER, SHOW VIEW ON *.* TO u', (new Semantics('mysql-5.6.51'))->inspect('grant create tablespace, create user, event, trigger, show view on *.* to u')->toString());
    }

    public function testNamedReadsANameByTheStatement(): void
    {
        self::assertSame('GRANT flush_tables ON *.* TO u', (new Semantics())->inspect('grant flush_tables on *.* to u')->toString());
        self::assertSame('GRANT flush_tables TO u', (new Semantics())->inspect('grant flush_tables to u')->toString());
    }

    public function testPrivilegesLowersTheLegacyList(): void
    {
        self::assertSame('GRANT ALL ON *.* TO u', (new Semantics('mysql-5.6.51'))->inspect('grant all privileges on *.* to u')->toString());
    }

    public function testWordsConfirmsTheOptionalWord(): void
    {
        self::assertSame('REVOKE ALL ON *.* FROM u', (new Semantics())->inspect('revoke all privileges on *.* from u')->toString());
    }

    public function testColumnsLowersTheColumnList(): void
    {
        self::assertSame('GRANT UPDATE (a, b) ON t TO u', (new Semantics('mysql-5.7.44'))->inspect('grant update (a, b) on t to u')->toString());
    }

    public function testColumnLowersBothColumnRules(): void
    {
        self::assertSame('GRANT REFERENCES (a) ON t TO u', (new Semantics('mysql-5.6.51'))->inspect('grant references (a) on t to u')->toString());
        self::assertSame('GRANT REFERENCES (a) ON t TO u', (new Semantics())->inspect('grant references (a) on t to u')->toString());
    }

    public function testLevelLowersEveryLevel(): void
    {
        self::assertSame('GRANT SELECT ON db.* TO u', (new Semantics('mysql-5.6.51'))->inspect('grant select on db.* to u')->toString());
        self::assertSame('GRANT SELECT ON db.t TO u', (new Semantics('mysql-5.6.51'))->inspect('grant select on db.t to u')->toString());
    }

    public function testKindLowersEveryKind(): void
    {
        self::assertSame('GRANT EXECUTE ON PROCEDURE p TO u', (new Semantics())->inspect('grant execute on procedure p to u')->toString());
    }

    public function testSpineFlattensTheList(): void
    {
        self::assertSame('GRANT SELECT, DELETE, USAGE ON *.* TO u', (new Semantics())->inspect('grant select, delete, usage on *.* to u')->toString());
    }
}
