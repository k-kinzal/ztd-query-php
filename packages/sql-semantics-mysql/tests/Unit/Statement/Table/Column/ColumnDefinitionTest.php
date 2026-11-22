<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ColumnDefinition::class)]
#[Medium]
final class ColumnDefinitionTest extends TestCase
{
    public function testDeriveElementDerivesTheSpecificationInTheTable(): void
    {
        $create = (new Inspection())->inspect('CREATE TABLE t (a INT, b INT AS (a * 2))');
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
        self::assertSame('CREATE TABLE t (t.a INT NOT NULL)', (new Inspection('5.7.44'))->inspect('CREATE TABLE t (t.a INT NOT NULL)')->toString());
    }
}
