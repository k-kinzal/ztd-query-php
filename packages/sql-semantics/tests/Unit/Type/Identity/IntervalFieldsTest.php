<?php

declare(strict_types=1);

namespace Tests\Unit\Type\Identity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Identity\IntervalFields;
use SqlSemantics\Type\Identity\IntervalStorage;

#[CoversClass(IntervalFields::class)]
#[Medium]
final class IntervalFieldsTest extends TestCase
{
    public function testRepresentsEveryPostgreSqlFieldRange(): void
    {
        self::assertSame(['', 'YEAR', 'MONTH', 'DAY', 'HOUR', 'MINUTE', 'SECOND', 'YEAR TO MONTH', 'DAY TO HOUR', 'DAY TO MINUTE', 'DAY TO SECOND', 'HOUR TO MINUTE', 'HOUR TO SECOND', 'MINUTE TO SECOND'], array_column(IntervalFields::cases(), 'value'));
    }

    public function testClassifiesDeclaredRangesAndDefaultsToAll(): void
    {
        $table = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTERVAL DAY TO SECOND, b INTERVAL YEAR, c INTERVAL)')->tables[0];
        $fields = array_map(static function ($column): IntervalFields {
            self::assertInstanceOf(IntervalStorage::class, $column->type->identity);
            return $column->type->identity->fields;
        }, $table->columns);
        self::assertSame([IntervalFields::DayToSecond, IntervalFields::Year, IntervalFields::All], $fields);
    }
}
