<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Ordering\SpecializedProjection;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthOperator;
use SqlSemantics\Platform\MySql\Ir\Predicate\Truth\TruthTest;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(SpecializedProjection::class)]
#[Medium]
final class SpecializedProjectionTest extends TestCase
{
    public function testLowerUnwrapsAGroupedTruthTest(): void
    {
        $inspection = new Inspection();
        $table = $inspection->inspect('CREATE TABLE app.events (id INT)');
        $bound = $inspection->inspect('SELECT ((id IS TRUE)) AS selected FROM app.events', [$table])->binding();
        $select = $bound->statement;
        self::assertInstanceOf(Select::class, $select);
        $item = $select->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        $expression = (new SpecializedProjection())->lower($item->expression, new SourceBinding($bound, $select->from));
        self::assertInstanceOf(TruthTest::class, $expression);
        self::assertSame(TruthOperator::True, $expression->operator);
    }
}
