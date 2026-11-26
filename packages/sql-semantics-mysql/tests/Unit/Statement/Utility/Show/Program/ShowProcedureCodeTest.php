<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowProcedureCode;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowProcedureCode::class)]
#[Medium]
final class ShowProcedureCodeTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Inspection())->diagnose('SHOW PROCEDURE CODE db.x');
        self::assertInstanceOf(ShowProcedureCode::class, $show->statement);
        self::assertSame('Pos', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW PROCEDURE CODE db.x', (new Inspection())->diagnose('SHOW PROCEDURE CODE db.x')->toString());
    }
}
