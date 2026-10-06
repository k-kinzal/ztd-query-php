<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\EnforcementAttribute;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;

#[CoversClass(EnforcementAttribute::class)]
#[Medium]
final class EnforcementAttributeTest extends TestCase
{
    public function testDeriveAttributeDerivesNothing(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT CHECK (a > 0) ENFORCED)');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $column = $statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $column);
        $attribute = $column->specification->columnAttributes()[1];

        self::assertInstanceOf(EnforcementAttribute::class, $attribute);
        self::assertTrue($attribute->enforced);
    }

    public function testRenderWritesTheAttribute(): void
    {
        self::assertSame('CREATE TABLE t (a INT CHECK (a > 0) ENFORCED)', (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT CHECK (a > 0) ENFORCED)')->toString());
    }
}
