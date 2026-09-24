<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\MySqlTable\Column\ColumnDefaultAssignment;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\MySql\Table\AlterTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ColumnDefaultAssignment::class)]
#[Medium]
final class ColumnDefaultAssignmentTest extends TestCase
{
    public function testReadsTheColumnAndDefault(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('ALTER TABLE t ALTER n SET DEFAULT 5');
        self::assertInstanceOf(AlterTableStatement::class, $statement);
        $alteration = $statement->alterations[0];
        self::assertInstanceOf(ColumnDefaultAssignment::class, $alteration);
        self::assertSame('n', $alteration->column);
        self::assertInstanceOf(Literal::class, $alteration->default);
        self::assertSame('5', $alteration->default->text);
    }

    public function testRejectsAnotherDialectExpression(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ColumnDefaultAssignment('n', $statement->outputs[0]->expression);
    }
}
