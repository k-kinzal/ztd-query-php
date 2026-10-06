<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OnUpdate;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(OnUpdate::class)]
#[Medium]
final class OnUpdateTest extends TestCase
{
    public function testDeriveAttributeDerivesTheValue(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $column = $statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $column);
        $attribute = $column->specification->columnAttributes()[0];

        self::assertInstanceOf(OnUpdate::class, $attribute);
        self::assertSame(Nullability::NotNull, $create->facts->scalar($attribute->value)->nullability);
    }

    public function testRenderWritesTheAttribute(): void
    {
        self::assertSame('CREATE TABLE t (a TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)', (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)')->toString());
    }
}
