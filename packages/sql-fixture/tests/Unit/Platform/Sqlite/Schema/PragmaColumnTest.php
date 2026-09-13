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
    #[\PHPUnit\Framework\Attributes\DataProvider('providerPragmaColumns')]
    public function testParseRetainsAllColumnMetadata(string $type, int $notNull, int $primaryKey, ?string $default, \SqlFixture\Schema\ColumnDefinition $expected): void
    {
        $column = (new Subject())->parse(['cid' => 0, 'name' => 'value', 'type' => $type, 'notnull' => $notNull, 'dflt_value' => $default, 'pk' => $primaryKey]);
        self::assertEquals($expected, $column);
    }

    /**
     * @return list<array{string, int, int, ?string, \SqlFixture\Schema\ColumnDefinition}>
     */
    public static function providerPragmaColumns(): array
    {
        return [
            ['', 0, 0, null, new \SqlFixture\Schema\ColumnDefinition('value', 'BLOB')],
            ['integer', 0, 1, null, new \SqlFixture\Schema\ColumnDefinition('value', 'INTEGER', nullable: false)],
            ['integer', 0, 2, null, new \SqlFixture\Schema\ColumnDefinition('value', 'INTEGER', nullable: false)],
            ['varchar ( 12 )', 0, 0, "'ready'", new \SqlFixture\Schema\ColumnDefinition('value', 'VARCHAR', length: 12, default: 'ready')],
            ['decimal ( 8 , 2 )', 1, 0, '-12.5', new \SqlFixture\Schema\ColumnDefinition('value', 'DECIMAL', precision: 8, scale: 2, nullable: false, default: -12.5)],
            ['text', 1, 0, null, new \SqlFixture\Schema\ColumnDefinition('value', 'TEXT', nullable: false)],
        ];
    }
}
