<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Account\PrivilegeRule;
use SqlSemantics\Statement\Fact\Warning;

#[CoversClass(PrivilegeRule::class)]
#[Medium]
final class PrivilegeRuleTest extends TestCase
{
    public function testItemsLowersTheList(): void
    {
        self::assertSame('GRANT SELECT, INSERT (a), CREATE ROLE, DROP ROLE ON *.* TO u', (new Semantics(Dialect::MySql))->analyze('grant select, insert (a), create role, drop role on *.* to u')->toString());
    }

    public function testItemLowersEveryStaticPrivilege(): void
    {
        self::assertSame('GRANT CREATE TABLESPACE, CREATE USER, EVENT, TRIGGER, SHOW VIEW ON *.* TO u', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('grant create tablespace, create user, event, trigger, show view on *.* to u')->toString());
    }

    #[DataProvider('providerSuperReleases')]
    public function testItemRetainsEachSuperDeprecationBeforePrivilegeValidation(string $release, int $count): void
    {
        $operation = (new Semantics(Dialect::MySql, $release))->analyze('GRANT SUPER,SUPER ON d.* TO u');

        self::assertSame(array_fill(0, $count, 'The SUPER privilege identifier is deprecated'), array_map(static fn (Warning $warning): string => $warning->message(), $operation->facts->warnings));
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function providerSuperReleases(): array
    {
        return [
            '5.6' => ['mysql-5.6.51', 0],
            '5.7' => ['mysql-5.7.44', 0],
            '8.0' => ['mysql-8.0.44', 2],
            '8.4' => ['mysql-8.4.7', 2],
            '9.1' => ['mysql-9.1.0', 2],
        ];
    }

    public function testNamedReadsANameByTheStatement(): void
    {
        self::assertSame('GRANT flush_tables ON *.* TO u', (new Semantics(Dialect::MySql))->analyze('grant flush_tables on *.* to u')->toString());
        self::assertSame('GRANT flush_tables TO u', (new Semantics(Dialect::MySql))->analyze('grant flush_tables to u')->toString());
    }

    public function testPrivilegesLowersTheLegacyList(): void
    {
        self::assertSame('GRANT ALL ON *.* TO u', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('grant all privileges on *.* to u')->toString());
    }

    public function testWordsConfirmsTheOptionalWord(): void
    {
        self::assertSame('REVOKE ALL ON *.* FROM u', (new Semantics(Dialect::MySql))->analyze('revoke all privileges on *.* from u')->toString());
    }

    public function testColumnsLowersTheColumnList(): void
    {
        self::assertSame('GRANT UPDATE (a, b) ON t TO u', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('grant update (a, b) on t to u')->toString());
    }

    public function testColumnLowersBothColumnRules(): void
    {
        self::assertSame('GRANT REFERENCES (a) ON t TO u', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('grant references (a) on t to u')->toString());
        self::assertSame('GRANT REFERENCES (a) ON t TO u', (new Semantics(Dialect::MySql))->analyze('grant references (a) on t to u')->toString());
    }

    public function testLevelLowersEveryLevel(): void
    {
        self::assertSame('GRANT SELECT ON db.* TO u', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('grant select on db.* to u')->toString());
        self::assertSame('GRANT SELECT ON db.t TO u', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('grant select on db.t to u')->toString());
    }

    public function testKindLowersEveryKind(): void
    {
        self::assertSame('GRANT EXECUTE ON PROCEDURE p TO u', (new Semantics(Dialect::MySql))->analyze('grant execute on procedure p to u')->toString());
    }

    public function testSpineFlattensTheList(): void
    {
        self::assertSame('GRANT SELECT, DELETE, USAGE ON *.* TO u', (new Semantics(Dialect::MySql))->analyze('grant select, delete, usage on *.* to u')->toString());
    }
}
