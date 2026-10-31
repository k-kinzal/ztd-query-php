<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredDefaultSetting;

#[CoversClass(DeferredDefaultSetting::class)]
#[Medium]
final class DeferredDefaultSettingTest extends TestCase
{
    public function testDeriveCommandRetainsTheFullDefaultAndItsTarget(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() ALTER TABLE t ALTER a SET DEFAULT (ROW(1,2)), FORCE');
        self::assertInstanceOf(M\Statement::class, $operation);
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation);
        $alter = $operation->body;
        self::assertInstanceOf(M\Alter\AlterTable::class, $alter);
        $default = $alter->commands[0];
        self::assertInstanceOf(DeferredDefaultSetting::class, $default);
        self::assertTrue($default->expression->hasBinding());
        self::assertCount(2, $alter->commands);
    }

    public function testRenderRetainsTheOperandRoleAndCompleteOwner(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() ALTER TABLE t ALTER a SET DEFAULT (ROW(1,2)), FORCE');
        self::assertInstanceOf(M\Statement::class, $operation);
        self::assertSame('CREATE PROCEDURE p() ALTER TABLE t ALTER a SET DEFAULT (ROW(1, 2)), FORCE', $operation->toString());
    }
}
