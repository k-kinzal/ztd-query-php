<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\Column\CollateAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;

#[CoversClass(CollateAttribute::class)]
#[Medium]
final class CollateAttributeTest extends TestCase
{
    public function testDeriveAttributeDerivesNothing(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a TEXT COLLATE utf8mb4_bin)');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $column = $statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $column);
        $attribute = $column->specification->columnAttributes()[0];

        self::assertInstanceOf(CollateAttribute::class, $attribute);
        self::assertSame('utf8mb4_bin', $attribute->collation->name?->value);
    }

    public function testRenderWritesTheAttribute(): void
    {
        self::assertSame('CREATE TABLE t (a TEXT COLLATE utf8mb4_bin)', (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a TEXT COLLATE utf8mb4_bin)')->toString());
    }
}
