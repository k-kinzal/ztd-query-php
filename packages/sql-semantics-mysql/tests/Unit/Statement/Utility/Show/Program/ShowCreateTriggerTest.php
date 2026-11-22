<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateTrigger;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ShowCreateTrigger::class)]
#[Medium]
final class ShowCreateTriggerTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Inspection())->inspect('SHOW CREATE TRIGGER db.x');
        self::assertInstanceOf(ShowCreateTrigger::class, $show->statement);
        self::assertSame('Trigger', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW CREATE TRIGGER db.x', (new Inspection())->inspect('SHOW CREATE TRIGGER db.x')->toString());
    }
}
