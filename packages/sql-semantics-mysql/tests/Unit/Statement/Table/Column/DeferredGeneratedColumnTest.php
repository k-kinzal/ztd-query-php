<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Table\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Table\Column\DeferredGeneratedColumn;

#[CoversClass(DeferredGeneratedColumn::class)]
#[Medium]
final class DeferredGeneratedColumnTest extends TestCase
{
    /**
     * @return iterable<array{DeferredGeneratedColumn}>
     */
    public static function providerColumns(): iterable
    {
        $query = new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]));
        yield [new DeferredGeneratedColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), new M\Expression\Operator\Arithmetic(M\Expression\Operator\ArithmeticOperator::Plus, new M\Literal\NumberLiteral('2'), $query))];
    }

    #[DataProvider('providerColumns')]
    public function testDataTypePreservesTheDeclaredType(DeferredGeneratedColumn $column): void
    {
        self::assertInstanceOf(M\Type\Integral::class, $column->dataType());
    }

    #[DataProvider('providerColumns')]
    public function testColumnAttributesPreservesTheWrittenList(DeferredGeneratedColumn $column): void
    {
        self::assertSame([], $column->columnAttributes());
    }

    #[DataProvider('providerColumns')]
    public function testRenderKeepsTheNestedQuery(DeferredGeneratedColumn $column): void
    {
        $out = new \SqlSemantics\Rendering\Output(new \SqlSemantics\Platform\MySql\Rendering\Codec(\SqlSemantics\Contract\GrammarRelease::MySql5744));
        $column->render($out);
        self::assertSame('INT AS (2 + (SELECT 1))', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }

    public function testRejectsABareSubqueryThatCannotSurviveDefinition(): void
    {
        $this->expectExceptionMessage('A deferred generation declaration retains a scalar-position subquery.');
        new DeferredGeneratedColumn(new M\Type\Integral(M\Type\Kind\IntegralKind::Int), new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))])));
    }

    public function testDeriveSpecificationAndRenderKeepTheCompleteDeferredColumn(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, '5.7.44');
        $operation = $semantics->analyze("CREATE PROCEDURE p() CREATE TABLE t (g INT AS (1+(SELECT * FROM source)) STORED NOT NULL COMMENT 'note')");
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation->statement);
        $body = $operation->statement->body;
        self::assertInstanceOf(M\Routine\Program\Write\DeferredCreateTable::class, $body);
        self::assertInstanceOf(M\Table\Column\ColumnDefinition::class, $body->elements[0]);
        $column = $body->elements[0]->specification;
        self::assertInstanceOf(DeferredGeneratedColumn::class, $column);
        self::assertInstanceOf(M\Type\Integral::class, $column->dataType());
        self::assertCount(2, $column->columnAttributes());
        self::assertStringContainsString('AS (1 + (SELECT * FROM `source`)) STORED NOT NULL', $operation->toString());
    }
}
