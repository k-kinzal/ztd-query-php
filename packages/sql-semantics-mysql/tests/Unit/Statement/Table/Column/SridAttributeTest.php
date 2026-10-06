<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\SridAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;

#[CoversClass(SridAttribute::class)]
#[Medium]
final class SridAttributeTest extends TestCase
{
    public function testDeriveAttributeDerivesNothing(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (g POINT SRID 4326)');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $column = $statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $column);
        $attribute = $column->specification->columnAttributes()[0];

        self::assertInstanceOf(SridAttribute::class, $attribute);
        self::assertSame('4326', $attribute->srid->text);
    }

    public function testRenderWritesTheAttribute(): void
    {
        self::assertSame('CREATE TABLE t (g POINT SRID 4326)', (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (g POINT SRID 4326)')->toString());
    }
}
