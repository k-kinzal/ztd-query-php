<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Conditional\MembershipBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(MembershipBinder::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class MembershipBinderTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'SELECT ROW(1,2) IN (ROW(3))'])]
    #[TestWith([Dialect::MySql, 'SELECT (1,2) IN (3)'])]
    #[TestWith([Dialect::Sqlite, 'SELECT (1,2) IN (3)'])]
    public function testBindDiagnosesIncompatibleRowWidths(Dialect $dialect, string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage('Compared row operands must have equal widths.');
        (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql, strict: false);
    }

    #[TestWith([Dialect::PostgreSql, 'SELECT 1 IN (1, NULL)', Nullability::MaybeNull])]
    #[TestWith([Dialect::MySql, 'SELECT 1 IN (1, NULL)', Nullability::MaybeNull])]
    #[TestWith([Dialect::Sqlite, 'SELECT 1 IN (1, NULL)', Nullability::MaybeNull])]
    #[TestWith([Dialect::Sqlite, 'SELECT NULL IN ()', Nullability::NotNull])]
    #[TestWith([Dialect::PostgreSql, 'SELECT NULL IN (1,2)', Nullability::AlwaysNull])]
    #[TestWith([Dialect::PostgreSql, 'SELECT 1 IN (NULL,NULL)', Nullability::AlwaysNull])]
    #[TestWith([Dialect::PostgreSql, 'SELECT ROW(1,NULL) IN (ROW(1,2))', Nullability::MaybeNull])]
    #[TestWith([Dialect::PostgreSql, 'SELECT 1 IN (2,3)', Nullability::NotNull])]
    public function testNullabilityDescribesPossibleMatchesWithoutEvaluatingThem(Dialect $dialect, string $sql, Nullability $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame($expected, $statement->outputs[0]->expression->nullability);
    }
}
