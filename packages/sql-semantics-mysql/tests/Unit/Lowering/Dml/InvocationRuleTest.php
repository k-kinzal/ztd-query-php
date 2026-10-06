<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Dml\InvocationRule;

#[CoversClass(InvocationRule::class)]
#[Medium]
final class InvocationRuleTest extends TestCase
{
    public function testEvaluationLowersEveryGeneration(): void
    {
        self::assertSame('DO 1', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('do 1')->toString());
        self::assertSame('DO 1 AS a', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('do 1 as a')->toString());
        self::assertSame('DO 1, 2', (new Semantics(Dialect::MySql))->analyze('do 1, 2')->toString());
    }

    public function testCallLowersBothGenerations(): void
    {
        self::assertSame('CALL p', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('call p')->toString());
        self::assertSame('CALL db.p(1)', (new Semantics(Dialect::MySql))->analyze('call db.p(1)')->toString());
    }

    public function testArgumentsLowersEveryList(): void
    {
        self::assertSame('CALL p', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('call p()')->toString());
        self::assertSame('CALL p', (new Semantics(Dialect::MySql))->analyze('call p()')->toString());
        self::assertSame('CALL p(1, 2)', (new Semantics(Dialect::MySql))->analyze('call p(1, 2)')->toString());
    }

    public function testImportLowersTheFiles(): void
    {
        self::assertSame('IMPORT TABLE FROM \'a\', \'b\'', (new Semantics(Dialect::MySql))->analyze('import table from \'a\', \'b\'')->toString());
    }

    public function testPreparedLowersPrepareExecuteAndDeallocate(): void
    {
        self::assertSame('PREPARE s FROM @q', (new Semantics(Dialect::MySql))->analyze('prepare s from @`q`')->toString());
        self::assertSame('DEALLOCATE PREPARE s', (new Semantics(Dialect::MySql))->analyze('deallocate prepare s')->toString());
    }

    public function testVariablesLowersTheUsingList(): void
    {
        self::assertSame('EXECUTE s USING @a, @`b c`', (new Semantics(Dialect::MySql))->analyze('execute s using @a, @\'b c\'')->toString());
    }
}
