<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Dml\Insert;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Dml\Insert\ValueGroups;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Scalar;

#[CoversClass(ValueGroups::class)]
#[Small]
final class ValueGroupsTest extends TestCase
{
    /**
     * @return iterable<string, array{Scalar}>
     */
    public static function providerGroups(): iterable
    {
        yield 'sum' => [new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Sum, [new M\Literal\NumberLiteral('1')])];
        yield 'window' => [new M\Call\Window\WindowFunction(M\Call\Window\WindowFunctionKind::RowNumber, [], new M\Call\Window\WindowSpec())];
        yield 'windowed sum' => [new M\Call\Aggregate\Aggregate(M\Call\Aggregate\AggregateFunction::Sum, [new M\Literal\NumberLiteral('1')], over: new M\Call\Window\WindowSpec())];
    }

    #[DataProvider('providerGroups')]
    public function testProblemTheOrdinaryRowRejectsAnAmbientGroupBeforeOperationConstruction(Scalar $group): void
    {
        $value = new M\Expression\Grouped($group);
        self::assertSame($group, (new ValueGroups())->problem($value)?->function);
        self::assertSame($value, (new M\Routine\Program\Write\DeferredInsertedRow([$value]))->values[0]);
        $this->expectExceptionMessage('A VALUES element has no ambient grouping or window role.');
        new M\Dml\Insert\InsertedRow([$value]);
    }

    #[DataProvider('providerGroups')]
    public function testProblemASubqueryOwnsItsGroupingScope(Scalar $group): void
    {
        $query = new M\Query\Select([], [new M\Query\SelectExpression($group)]);
        $value = new M\Expression\Subquery\ScalarSubquery($query);
        self::assertNull((new ValueGroups())->problem($value));
        self::assertSame($value, (new M\Dml\Insert\InsertedRow([$value]))->values[0]);
        $this->expectExceptionMessage('A deferred VALUES row retains a preparation condition.');
        new M\Routine\Program\Write\DeferredInsertedRow([$value]);
    }
}
