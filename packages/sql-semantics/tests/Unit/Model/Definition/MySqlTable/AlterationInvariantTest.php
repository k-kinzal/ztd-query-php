<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\MySqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AlterationInvariant::class)]
#[Medium]
final class AlterationInvariantTest extends TestCase
{
    public function testNameRejectsAnEmptyIdentifier(): void
    {
        $this->expectException(InvalidStructure::class);
        AlterationInvariant::name('');
    }

    public function testNamesReturnsDistinctNames(): void
    {
        self::assertSame(['a', 'b'], AlterationInvariant::names(['a', 'b']));
    }

    public function testNamesRejectsARepeatedName(): void
    {
        $this->expectException(InvalidStructure::class);
        AlterationInvariant::names(['a', 'A']);
    }

    public function testColumnRejectsAnotherDialect(): void
    {
        $column = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')->tables[0]->columns[0];
        $this->expectException(InvalidStructure::class);
        AlterationInvariant::column($column, []);
    }

    public function testExpressionAcceptsAMySqlExpression(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $statement);
        AlterationInvariant::expression($statement->outputs[0]->expression);
        self::assertSame(Dialect::MySql, $statement->outputs[0]->expression->type->dialect);
    }

    public function testPositiveRejectsZero(): void
    {
        $this->expectException(InvalidStructure::class);
        AlterationInvariant::positive(0);
    }
}
