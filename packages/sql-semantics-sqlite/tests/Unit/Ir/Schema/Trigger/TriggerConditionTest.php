<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\RowImage;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerCondition;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField;

#[CoversClass(TriggerCondition::class)]
#[Small]
final class TriggerConditionTest extends TestCase
{
    public function testKeepsTheCheckedNullMode(): void
    {
        $field = new TriggerField(RowImage::New, 2);
        $condition = new TriggerCondition($field, false);

        self::assertSame($field, $condition->field);
        self::assertFalse($condition->isNull);
    }
}
