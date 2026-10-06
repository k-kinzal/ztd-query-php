<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Direction;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;

#[CoversClass(ColumnPart::class)]
#[Medium]
final class ColumnPartTest extends TestCase
{
    public function testDeriveKeyPartDerivesNothing(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a TEXT, KEY (a(4) DESC))');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $element1 = $statement->elements[1];
        self::assertInstanceOf(IndexDefinition::class, $element1);
        $part = $element1->parts[0];

        self::assertInstanceOf(ColumnPart::class, $part);
        self::assertSame(Direction::Descending, $part->direction);
        self::assertSame([], $create->facts->diagnostics);
    }

    public function testRenderWritesTheColumnTheLengthAndTheDirection(): void
    {
        self::assertSame('CREATE TABLE t (a TEXT, INDEX (a(4) ASC))', (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a TEXT, KEY (a(4) ASC))')->toString());
    }
}
