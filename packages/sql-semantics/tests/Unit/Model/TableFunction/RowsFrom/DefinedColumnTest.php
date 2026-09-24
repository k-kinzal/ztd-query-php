<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction\RowsFrom;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Model\TableFunction\RowsFrom\DefinedColumn;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(DefinedColumn::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class DefinedColumnTest extends TestCase
{
    public function testKeepsTheNameAndType(): void
    {
        $column = new DefinedColumn('a', TypeDescriptor::builtin(Dialect::PostgreSql, 'text'));
        self::assertSame('a', $column->name);
        self::assertSame('text', $column->type->name);
    }

    public function testRejectsAnEmptyName(): void
    {
        $this->expectException(InvalidStructure::class);
        new DefinedColumn('', TypeDescriptor::builtin(Dialect::PostgreSql, 'text'));
    }
}
