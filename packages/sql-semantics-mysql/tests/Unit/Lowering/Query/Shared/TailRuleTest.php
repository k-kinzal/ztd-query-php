<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Shared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Query\Shared\TailRule;

#[CoversClass(TailRule::class)]
#[Medium]
final class TailRuleTest extends TestCase
{
    public function testLimitLowersEveryForm(): void
    {
        self::assertSame('SELECT a FROM t LIMIT 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select a from t limit 1')->toString());
        self::assertSame('SELECT a FROM t LIMIT 18446744073709551615 OFFSET 2', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select a from t limit 18446744073709551615 offset 2')->toString());
    }

    public function testOptionsLowersTheOffsetSpellings(): void
    {
        self::assertSame('SELECT a FROM t LIMIT 2, 3', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select a from t limit 2, 3')->toString());
    }

    public function testValueLowersEveryOperand(): void
    {
        self::assertSame('SELECT a FROM t LIMIT ?, n', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select a from t limit ?, n')->toString());
    }

    public function testProcedureLowersTheArguments(): void
    {
        self::assertSame('SELECT a FROM t PROCEDURE ANALYSE(1)', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select a from t procedure analyse(1)')->toString());
    }

    public function testIntoLowersEveryPosition(): void
    {
        self::assertSame('SELECT a INTO @x FROM t', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select a into @x from t')->toString());
        self::assertSame('SELECT a FROM t INTO @x', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select a from t into @x')->toString());
    }

    public function testDestinationLowersTheDestinations(): void
    {
        self::assertSame('SELECT a FROM t INTO DUMPFILE \'f\'', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select a from t into dumpfile \'f\'')->toString());
    }

    public function testVariablesLowersUserAndProgramVariables(): void
    {
        self::assertSame('SELECT a, b FROM t INTO @x, y', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('select a, b from t into @`x`, y')->toString());
    }

    public function testLockingLowersEveryForm(): void
    {
        self::assertSame('SELECT a FROM t FOR SHARE NOWAIT FOR UPDATE LOCK IN SHARE MODE', (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze('select a from t for share nowait for update lock in share mode')->toString());
        self::assertSame('SELECT a FROM t LOCK IN SHARE MODE', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('select a from t lock in share mode')->toString());
    }

    public function testLockLowersOneLockingClause(): void
    {
        self::assertSame('SELECT a FROM t FOR UPDATE SKIP LOCKED', (new Semantics(Dialect::MySql, 'mysql-8.0.44'))->analyze('select a from t for update skip locked')->toString());
    }
}
