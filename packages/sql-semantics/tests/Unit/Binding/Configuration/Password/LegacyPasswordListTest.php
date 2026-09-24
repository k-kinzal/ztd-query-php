<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Configuration\Password;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Configuration\Password\LegacyPasswordList;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(LegacyPasswordList::class)]
#[Medium]
final class LegacyPasswordListTest extends TestCase
{
    public function testBindPreservesPasswordAndVariableAssignmentOrder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build());
        $statement = $binder->bind("SET @x = 1, PASSWORD = PASSWORD('new'), @x = 2, PASSWORD FOR 'u' = '*hash'", strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\Password\SetAccountOptionsStatement::class, $statement);
        self::assertCount(4, $statement->operations);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement->operations[0]);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\Password\SetDerivedPasswordStatement::class, $statement->operations[1]);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement->operations[2]);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\Password\SetPasswordHashStatement::class, $statement->operations[3]);
        self::assertSame($statement->toString(), $binder->bind($statement->toString(), strict: false)->toString());
    }

    public function testBindRetainsTheInheritedSystemVariableScope(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build());
        $statement = $binder->bind("SET GLOBAL max_connections = 10, PASSWORD = '*hash', max_allowed_packet = 20");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\Password\SetAccountOptionsStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\SetStatement::class, $statement->operations[2]);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Setting::class, $statement->operations[2]->settings[0]);
        self::assertSame(\SqlSemantics\Model\Configuration\SettingScope::Global, $statement->operations[2]->settings[0]->scope);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testBindReturnsASingleCredentialItself(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("SET PASSWORD = '*abc'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\Password\SetPasswordHashStatement::class, $statement);
        self::assertSame("SET PASSWORD = '*abc'", $statement->toString());
    }

    #[TestWith(["SET PASSWORD = '*one', PASSWORD FOR u = '*two'", "SET PASSWORD = '*one', PASSWORD FOR 'u' = '*two'"])]
    #[TestWith(["SET GLOBAL a = 1, @u = 2, b = 3, PASSWORD = '*x'", "SET GLOBAL `a` = 1, @`u` = 2, GLOBAL `b` = 3, PASSWORD = '*x'"])]
    #[TestWith(["SET global a = 1, PASSWORD = '*x', b = 2", "SET GLOBAL `a` = 1, PASSWORD = '*x', GLOBAL `b` = 2"])]
    #[TestWith(["SET @@global.a = 1, PASSWORD = '*x', b = 2", "SET GLOBAL `a` = 1, PASSWORD = '*x', SESSION `b` = 2"])]
    #[TestWith(["SET a = 1, PASSWORD = '*x'", "SET `a` = 1, PASSWORD = '*x'"])]
    #[TestWith(["SET PASSWORD = PASSWORD('x'), @u = 1", "SET PASSWORD = PASSWORD('x'), @`u` = 1"])]
    public function testBindCarriesScopesAcrossPasswordClauses(string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind($sql);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\Password\SetAccountOptionsStatement::class, $statement);
        self::assertSame($expected, $statement->toString());
    }
}
