<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Conditional;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Conditional\ComparisonNullability;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(ComparisonNullability::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class ComparisonNullabilityTest extends TestCase
{
    #[TestWith(['SELECT ROW(NULL, 1)', Nullability::NotNull, Nullability::MaybeNull])]
    #[TestWith(['SELECT ROW(1, 2)', Nullability::NotNull, Nullability::NotNull])]
    #[TestWith(['SELECT NULL', Nullability::AlwaysNull, Nullability::AlwaysNull])]
    #[TestWith(['SELECT ROW(ROW(NULL, 1), 2)', Nullability::NotNull, Nullability::MaybeNull])]
    public function testOfDistinguishesRecordAndFieldNullFacts(string $sql, Nullability $record, Nullability $comparison): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        $value = $query->outputs[0]->expression;
        self::assertSame($record, $value->nullability);
        self::assertSame($comparison, ComparisonNullability::of($value));
    }
}
