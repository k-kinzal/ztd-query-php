<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Show\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Program\ShowCreateEvent;

#[CoversClass(ShowCreateEvent::class)]
#[Medium]
final class ShowCreateEventTest extends TestCase
{
    public function testDeriveStatementRecordsTheRows(): void
    {
        $show = (new Semantics())->inspect('SHOW CREATE EVENT db.x');
        self::assertInstanceOf(ShowCreateEvent::class, $show->statement);
        self::assertSame('Event', $show->field(0)->name?->value);
    }

    public function testRenderWritesTheStatement(): void
    {
        self::assertSame('SHOW CREATE EVENT db.x', (new Semantics())->inspect('SHOW CREATE EVENT db.x')->toString());
    }
}
