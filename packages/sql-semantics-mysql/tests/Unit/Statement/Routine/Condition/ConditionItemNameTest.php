<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Condition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\ConditionItemName;

#[CoversClass(ConditionItemName::class)]
#[Small]
final class ConditionItemNameTest extends TestCase
{
    public function testCasesHoldTheirKeywords(): void
    {
        self::assertSame(
            [
                'CLASS_ORIGIN',
                'SUBCLASS_ORIGIN',
                'CONSTRAINT_CATALOG',
                'CONSTRAINT_SCHEMA',
                'CONSTRAINT_NAME',
                'CATALOG_NAME',
                'SCHEMA_NAME',
                'TABLE_NAME',
                'COLUMN_NAME',
                'CURSOR_NAME',
                'MESSAGE_TEXT',
                'MYSQL_ERRNO',
                'RETURNED_SQLSTATE',
            ],
            array_map(static fn (ConditionItemName $item): string => $item->value, ConditionItemName::cases()),
        );
    }
}
