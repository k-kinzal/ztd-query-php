<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\SetOperator;
use SqlSemantics\Model\Statement\CompoundStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetOperator::class)]
#[Medium]
final class SetOperatorTest extends TestCase
{
    public function testRepresentsEverySetOperation(): void
    {
        self::assertSame(['UNION', 'UNION ALL', 'INTERSECT', 'INTERSECT ALL', 'EXCEPT', 'EXCEPT ALL'], array_column(SetOperator::cases(), 'value'));
    }

    #[TestWith([SetOperator::Union])]
    #[TestWith([SetOperator::UnionAll])]
    #[TestWith([SetOperator::Intersect])]
    #[TestWith([SetOperator::IntersectAll])]
    #[TestWith([SetOperator::Except])]
    #[TestWith([SetOperator::ExceptAll])]
    public function testBindsTheOperatorFromItsSpelling(SetOperator $operator): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 ' . $operator->value . ' SELECT 2');
        self::assertInstanceOf(CompoundStatement::class, $statement);
        self::assertSame($operator, $statement->setOperator);
        self::assertSame('SELECT 1 ' . $operator->value . ' SELECT 2', $statement->toString());
    }
}
