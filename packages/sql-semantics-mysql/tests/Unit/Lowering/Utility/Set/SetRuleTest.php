<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Utility\Set\SetRule;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;

#[CoversClass(SetRule::class)]
#[Medium]
final class SetRuleTest extends TestCase
{
    public function testStatementLowersEveryForm(): void
    {
        self::assertSame('SET TRANSACTION READ WRITE', (new Semantics(Dialect::MySql))->analyze('set transaction read write')->toString());
        self::assertSame('SET GLOBAL a = 1, b = 2', (new Semantics(Dialect::MySql))->analyze('set global a = 1, b = 2')->toString());
        self::assertSame("SET PASSWORD = PASSWORD('x')", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('set password = password(\'x\')')->toString());
    }

    public function testUnscopedRoutesALonePasswordToTheAccountFamily(): void
    {
        self::assertNotInstanceOf(SetVariables::class, (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("SET PASSWORD = 'x'")->statement);
    }

    public function testScopedLowersTheScopedTransaction(): void
    {
        self::assertSame('SET PERSIST TRANSACTION ISOLATION LEVEL SERIALIZABLE', (new Semantics(Dialect::MySql))->analyze('set persist transaction isolation level serializable')->toString());
    }

    public function testContinuedLowersEveryItem(): void
    {
        self::assertSame('SET a = 1, GLOBAL b = 2, @c = 3, NAMES utf8', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('set a = 1, global b = 2, @c = 3, names utf8')->toString());
    }

    public function testScopeMapsLocalToSession(): void
    {
        self::assertSame('SET SESSION a = 1', (new Semantics(Dialect::MySql))->analyze('set local a = 1')->toString());
    }

    public function testCharacteristicsKeepTheOrder(): void
    {
        self::assertSame('SET TRANSACTION READ ONLY, ISOLATION LEVEL READ COMMITTED', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('set transaction read only, isolation level read committed')->toString());
    }

    public function testOptionalLowersTheSecondCharacteristic(): void
    {
        self::assertSame('SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED, READ WRITE', (new Semantics(Dialect::MySql))->analyze('set transaction isolation level read uncommitted, read write')->toString());
    }

    public function testCharacteristicLowersEveryLevel(): void
    {
        self::assertSame('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ', (new Semantics(Dialect::MySql))->analyze('set transaction isolation level repeatable read')->toString());
    }
}
