<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\Schema\PragmaColumn as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\Sqlite\Schema\PragmaSchema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TableSchema::class)]
final class PragmaColumnTest extends TestCase
{
    public function testParseReadsDimensionsAndNullability(): void
    {
        $column = (new Subject())->parse(['cid' => 1, 'name' => 'amount', 'type' => 'DECIMAL(8,2)', 'notnull' => 1, 'dflt_value' => '12.5', 'pk' => 0]);
        self::assertSame('amount', $column->name);
        self::assertSame(8, $column->precision);
        self::assertSame(2, $column->scale);
        self::assertFalse($column->nullable);
        self::assertSame(12.5, $column->default);
    }
}
