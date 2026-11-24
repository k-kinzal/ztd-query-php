<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsertField;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsertRow;

#[CoversClass(TriggerInsertRow::class)]
#[Small]
final class TriggerInsertRowTest extends TestCase
{
    public function testColumnsFollowsTheWrittenOrderOfTheTuple(): void
    {
        $last = new TriggerInsertField(3, new NullLiteral());
        $first = new TriggerInsertField(1, new NullLiteral());
        $row = new TriggerInsertRow([$last, $first]);

        self::assertSame([3, 1], $row->columns());
        self::assertSame([$last, $first], $row->fields);
    }
}
