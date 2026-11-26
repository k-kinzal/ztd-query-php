<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Aggregation\Aggregate;
use SqlSemantics\Platform\MySql\Ir\Aggregation\AggregateOperator;
use SqlSemantics\Platform\MySql\Ir\Aggregation\GroupedLowering;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(GroupedLowering::class)]
#[Medium]
final class GroupedLoweringTest extends TestCase
{
    public function testAppliesRecognizesTheGlobalAggregate(): void
    {
        $select = (new Inspection())->inspect('SELECT count(*)')->statement;
        self::assertInstanceOf(Select::class, $select);
        self::assertTrue((new GroupedLowering())->applies($select));
    }

    public function testLowerBindsTheArgumentAndResultName(): void
    {
        $inspector = new Inspection();
        $declaration = $inspector->inspect('CREATE TABLE app.events (id INT, amount INT)');
        $operation = $inspector->inspect('SELECT SUM(amount) AS total FROM app.events GROUP BY id', [$declaration]);
        self::assertInstanceOf(Select::class, $operation->statement);

        $query = (new GroupedLowering())->lower($operation->binding(), $operation->statement);
        $field = $query->columns[0];
        self::assertSame('total', $field->name->value);
        self::assertInstanceOf(Aggregate::class, $field->value);
        self::assertSame(AggregateOperator::Sum, $field->value->operator);
        self::assertSame(1, $field->value->argument?->slot);
    }
}
