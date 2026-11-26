<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateFunction;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowCreateFunction::class)]
#[Medium]
final class ShowCreateFunctionTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Inspection())->inspect('SHOW CREATE FUNCTION db.x');
        self::assertInstanceOf(ShowCreateFunction::class, $show->statement);
        self::assertSame('Function', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW CREATE FUNCTION db.x', (new Inspection())->inspect('SHOW CREATE FUNCTION db.x')->toString());
    }
}
