<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupBinding;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupFilterLowering;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Grouped;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(GroupFilterLowering::class)]
#[Medium]
final class GroupFilterLoweringTest extends TestCase
{
    public function testLowerResolvesTheCountArgumentAndThreshold(): void
    {
        $inspector = new Inspection();
        $table = $inspector->inspect('CREATE TABLE main.items (id)');
        $operation = $inspector->inspect('SELECT COUNT(*) FROM main.items HAVING (?02 < (COUNT(id)))', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertNotNull($operation->statement->having);
        $source = new SourceBinding($operation->binding(), $operation->statement->from);
        $binding = new GroupBinding($operation->binding(), $source);
        $filter = (new GroupFilterLowering())->lower($operation->statement->having, $binding, $source);

        self::assertSame(0, $filter->count->argument?->slot);
        self::assertSame(ComparisonOperator::Less, $filter->operator);
        self::assertInstanceOf(Parameter::class, $filter->threshold);
        self::assertSame('02', $filter->threshold->label);
        self::assertTrue($filter->thresholdFirst);
    }

    public function testOperandFindsTheCountInsideGrouping(): void
    {
        $operation = (new Inspection())->inspect('SELECT COUNT(*) HAVING (COUNT(*))');
        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(Grouped::class, $operation->statement->having);

        self::assertInstanceOf(FunctionCall::class, (new GroupFilterLowering())->operand($operation->statement->having));
    }
}
