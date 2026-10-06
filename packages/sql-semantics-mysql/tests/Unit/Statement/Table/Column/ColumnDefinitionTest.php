<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(ColumnDefinition::class)]
#[Medium]
final class ColumnDefinitionTest extends TestCase
{
    public function testDeriveElementDerivesTheSpecificationInTheTable(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE TABLE t (a INT, b INT AS (a * 2))');
        $statement = $create->statement;
        self::assertInstanceOf(CreateTable::class, $statement);
        $element1 = $statement->elements[1];
        self::assertInstanceOf(ColumnDefinition::class, $element1);
        $generated = $element1->specification;

        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Table\Column\GeneratedColumn::class, $generated);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic::class, $generated->expression);
        $resolution = $create->facts->scalar($generated->expression->left)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame($create->declarations()[0]->columns[0], $resolution->slot->column);
    }

    public function testRenderWritesTheQualifiedNameAndTheSpecification(): void
    {
        self::assertSame('CREATE TABLE t (t.a INT NOT NULL)', (new Semantics(Dialect::MySql, '5.7.44'))->analyze('CREATE TABLE t (t.a INT NOT NULL)')->toString());
    }
}
