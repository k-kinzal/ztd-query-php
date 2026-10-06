<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowFunctionCode;

#[CoversClass(ShowFunctionCode::class)]
#[Medium]
final class ShowFunctionCodeTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics(Dialect::MySql))->analyze('SHOW FUNCTION CODE db.x');
        self::assertInstanceOf(ShowFunctionCode::class, $show->statement);
        self::assertSame('Pos', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW FUNCTION CODE db.x', (new Semantics(Dialect::MySql))->analyze('SHOW FUNCTION CODE db.x')->toString());
    }
}
