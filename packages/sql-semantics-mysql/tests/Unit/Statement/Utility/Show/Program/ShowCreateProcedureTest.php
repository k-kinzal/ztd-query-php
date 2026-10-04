<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateProcedure;

#[CoversClass(ShowCreateProcedure::class)]
#[Medium]
final class ShowCreateProcedureTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW CREATE PROCEDURE db.x');
        self::assertInstanceOf(ShowCreateProcedure::class, $show->statement);
        self::assertSame('Procedure', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW CREATE PROCEDURE db.x', (new Semantics(Dialect::MySql))->analyze('SHOW CREATE PROCEDURE db.x')->toString());
    }
}
