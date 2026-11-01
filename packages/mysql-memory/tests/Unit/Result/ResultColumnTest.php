<?php

declare(strict_types=1);

namespace Tests\Unit\Result;

use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\ResultColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(ResultColumn::class)]
#[Small]
final class ResultColumnTest extends TestCase
{
    public function testBinaryIsTrueForTheBinaryCollation(): void
    {
        $column = new ResultColumn('id', Field::Long, 11, 0, ColumnFlag::NotNull->value, 63);

        self::assertTrue($column->binary());
    }

    public function testBinaryIsFalseForACharacterCollation(): void
    {
        $column = new ResultColumn('name', Field::VarString, 80, 0, 0, 255, 'name', 't', 't', 'd');

        self::assertFalse($column->binary());
        self::assertSame('name', $column->originalName);
        self::assertSame('t', $column->table);
        self::assertSame('t', $column->originalTable);
        self::assertSame('d', $column->schema);
    }

    public function testUnsignedReadsTheUnsignedFlag(): void
    {
        $column = new ResultColumn('n', Field::LongLong, 20, 0, ColumnFlag::NotNull->value | ColumnFlag::Unsigned->value, 63);

        self::assertTrue($column->unsigned());
    }

    public function testUnsignedIsFalseWithoutTheFlag(): void
    {
        $column = new ResultColumn('n', Field::LongLong, 20, 0, ColumnFlag::NotNull->value | ColumnFlag::Numeric->value, 63);

        self::assertFalse($column->unsigned());
        self::assertSame('', $column->originalName);
        self::assertSame('', $column->schema);
    }
}
