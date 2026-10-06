<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\FormatAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\Kind\ColumnFormat;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;

#[CoversClass(FormatAttribute::class)]
#[Medium]
final class FormatAttributeTest extends TestCase
{
    public function testDeriveAttributeDerivesNothing(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT COLUMN_FORMAT DYNAMIC)');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $column = $statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $column);
        $attribute = $column->specification->columnAttributes()[0];

        self::assertInstanceOf(FormatAttribute::class, $attribute);
        self::assertSame(ColumnFormat::Dynamic, $attribute->format);
    }

    public function testRenderWritesTheAttribute(): void
    {
        self::assertSame('CREATE TABLE t (a INT COLUMN_FORMAT DYNAMIC)', (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT COLUMN_FORMAT DYNAMIC)')->toString());
    }
}
