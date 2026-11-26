<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\Count;
use SqlSemantics\Platform\Sqlite\Ir\Aggregation\GroupedLowering;
use SqlSemantics\Platform\Sqlite\Ir\Collation;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Testing\Sqlite\Inspection;

#[CoversClass(GroupedLowering::class)]
#[Medium]
final class GroupedLoweringTest extends TestCase
{
    public function testAppliesRecognizesTheGlobalCount(): void
    {
        $statement = (new Inspection())->inspect('SELECT count(*)')->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertTrue((new GroupedLowering())->applies($statement));
    }

    public function testLowerBindsGroupingAndCountCollations(): void
    {
        $inspector = new Inspection();
        $table = $inspector->inspect('CREATE TABLE main.items (a COLLATE NOCASE, b COLLATE RTRIM)');
        $operation = $inspector->inspect('SELECT count(DISTINCT b) AS n FROM main.items GROUP BY a', [$table]);
        self::assertInstanceOf(Select::class, $operation->statement);

        $query = (new GroupedLowering())->lower($operation->binding(), $operation->statement);
        self::assertSame(Collation::NoCase, $query->keys[0]->collation);
        $count = $query->columns[0]->value;
        self::assertInstanceOf(Count::class, $count);
        self::assertSame(Collation::RightTrim, $count->distinctCollation);
    }
}
