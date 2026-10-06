<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DefaultLiteral;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;

#[CoversClass(DefaultLiteral::class)]
#[Medium]
final class DefaultLiteralTest extends TestCase
{
    public function testDeriveAttributeDerivesTheValue(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT DEFAULT -1)');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $column = $statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $column);
        $attribute = $column->specification->columnAttributes()[0];

        self::assertInstanceOf(DefaultLiteral::class, $attribute);
        self::assertSame($create->facts->scalar($attribute->value)->nullability, \SqlSemantics\Statement\Type\Nullability::NotNull);
    }

    public function testRenderWritesTheAttribute(): void
    {
        self::assertSame('CREATE TABLE t (a INT DEFAULT - 1)', (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT DEFAULT -1)')->toString());
    }
}
