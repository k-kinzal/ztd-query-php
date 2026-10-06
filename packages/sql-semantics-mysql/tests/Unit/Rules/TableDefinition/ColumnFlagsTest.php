<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\TableDefinition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ColumnFlags;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(ColumnFlags::class)]
#[Medium]
final class ColumnFlagsTest extends TestCase
{
    public function testFlagsAppliesTheAttributesInOrder(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT PRIMARY KEY NULL)');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);

        $element0 = $statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $element0);
        self::assertSame([false, true, true], (new ColumnFlags())->flags($element0->specification));
    }

    public function testNullabilityFollowsTheFlags(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT NOT NULL NULL, b SERIAL, c TIMESTAMP, d INT AUTO_INCREMENT)');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $flags = new ColumnFlags();

        $element0 = $statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $element0);
        self::assertSame(Nullability::Nullable, $flags->nullability($element0->specification));
        $element1 = $statement->elements[1];
        self::assertInstanceOf(ColumnDefinition::class, $element1);
        self::assertSame(Nullability::NotNull, $flags->nullability($element1->specification));
        $element2 = $statement->elements[2];
        self::assertInstanceOf(ColumnDefinition::class, $element2);
        self::assertSame(Nullability::Dependent, $flags->nullability($element2->specification));
        $element3 = $statement->elements[3];
        self::assertInstanceOf(ColumnDefinition::class, $element3);
        self::assertSame(Nullability::NotNull, $flags->nullability($element3->specification));
        self::assertSame(Nullability::NotNull, $flags->nullability($element0->specification, true));
    }

    public function testInvisibleFollowsTheLastVisibilityAttribute(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT INVISIBLE VISIBLE, b INT INVISIBLE)');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);

        $element0 = $statement->elements[0];
        self::assertInstanceOf(ColumnDefinition::class, $element0);
        self::assertFalse((new ColumnFlags())->invisible($element0->specification));
        $element1 = $statement->elements[1];
        self::assertInstanceOf(ColumnDefinition::class, $element1);
        self::assertTrue((new ColumnFlags())->invisible($element1->specification));
    }
}
