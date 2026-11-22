<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Dml\InvocationRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(InvocationRule::class)]
#[Medium]
final class InvocationRuleTest extends TestCase
{
    public function testEvaluationLowersEveryGeneration(): void
    {
        self::assertSame('DO 1', (new Inspection('mysql-5.6.51'))->inspect('do 1')->toString());
        self::assertSame('DO 1 AS a', (new Inspection('mysql-5.7.44'))->inspect('do 1 as a')->toString());
        self::assertSame('DO 1, 2', (new Inspection())->inspect('do 1, 2')->toString());
    }

    public function testCallLowersBothGenerations(): void
    {
        self::assertSame('CALL p', (new Inspection('mysql-5.7.44'))->inspect('call p')->toString());
        self::assertSame('CALL db.p(1)', (new Inspection())->inspect('call db.p(1)')->toString());
    }

    public function testArgumentsLowersEveryList(): void
    {
        self::assertSame('CALL p', (new Inspection('mysql-5.6.51'))->inspect('call p()')->toString());
        self::assertSame('CALL p', (new Inspection())->inspect('call p()')->toString());
        self::assertSame('CALL p(1, 2)', (new Inspection())->inspect('call p(1, 2)')->toString());
    }

    public function testImportLowersTheFiles(): void
    {
        self::assertSame('IMPORT TABLE FROM \'a\', \'b\'', (new Inspection())->inspect('import table from \'a\', \'b\'')->toString());
    }

    public function testPreparedLowersPrepareExecuteAndDeallocate(): void
    {
        self::assertSame('PREPARE s FROM @q', (new Inspection())->inspect('prepare s from @`q`')->toString());
        self::assertSame('DEALLOCATE PREPARE s', (new Inspection())->inspect('deallocate prepare s')->toString());
    }

    public function testVariablesLowersTheUsingList(): void
    {
        self::assertSame('EXECUTE s USING @a, @`b c`', (new Inspection())->inspect('execute s using @a, @\'b c\'')->toString());
    }
}
