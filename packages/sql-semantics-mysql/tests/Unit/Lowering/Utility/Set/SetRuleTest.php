<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Utility\Set\SetRule;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(SetRule::class)]
#[Medium]
final class SetRuleTest extends TestCase
{
    public function testStatementLowersEveryForm(): void
    {
        self::assertSame('SET TRANSACTION READ WRITE', (new Inspection())->inspect('set transaction read write')->toString());
        self::assertSame('SET GLOBAL a = 1, b = 2', (new Inspection())->inspect('set global a = 1, b = 2')->toString());
        self::assertSame("SET PASSWORD = PASSWORD('x')", (new Inspection('mysql-5.7.44'))->inspect('set password = password(\'x\')')->toString());
    }

    public function testUnscopedRoutesALonePasswordToTheAccountFamily(): void
    {
        self::assertNotInstanceOf(SetVariables::class, (new Inspection('mysql-5.6.51'))->inspect("SET PASSWORD = 'x'")->statement);
    }

    public function testScopedLowersTheScopedTransaction(): void
    {
        self::assertSame('SET PERSIST TRANSACTION ISOLATION LEVEL SERIALIZABLE', (new Inspection())->inspect('set persist transaction isolation level serializable')->toString());
    }

    public function testContinuedLowersEveryItem(): void
    {
        self::assertSame('SET a = 1, GLOBAL b = 2, @c = 3, NAMES utf8', (new Inspection('mysql-5.6.51'))->inspect('set a = 1, global b = 2, @c = 3, names utf8')->toString());
    }

    public function testScopeMapsLocalToSession(): void
    {
        self::assertSame('SET SESSION a = 1', (new Inspection())->inspect('set local a = 1')->toString());
    }

    public function testCharacteristicsKeepTheOrder(): void
    {
        self::assertSame('SET TRANSACTION READ ONLY, ISOLATION LEVEL READ COMMITTED', (new Inspection('mysql-5.7.44'))->inspect('set transaction read only, isolation level read committed')->toString());
    }

    public function testOptionalLowersTheSecondCharacteristic(): void
    {
        self::assertSame('SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED, READ WRITE', (new Inspection())->inspect('set transaction isolation level read uncommitted, read write')->toString());
    }

    public function testCharacteristicLowersEveryLevel(): void
    {
        self::assertSame('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ', (new Inspection())->inspect('set transaction isolation level repeatable read')->toString());
    }
}
