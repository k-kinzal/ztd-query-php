<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerInsertField;

#[CoversClass(TriggerInsertField::class)]
#[Small]
final class TriggerInsertFieldTest extends TestCase
{
    public function testKeepsTheDestinationAndValueInTheSameAssignment(): void
    {
        $value = new NullLiteral();
        $field = new TriggerInsertField(2, $value);

        self::assertSame(2, $field->column);
        self::assertSame($value, $field->value);
    }
}
