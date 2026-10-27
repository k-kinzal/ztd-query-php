<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Variable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Variable\ParameterBinding;

#[CoversClass(ParameterBinding::class)]
#[Medium]
final class ParameterBindingTest extends TestCase
{
    public function testPositionFollowsWrittenOrderAcrossNestedScopes(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('SELECT ? FROM (SELECT ? AS a) t WHERE ?');
        $select = $operation;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\QueryRoot::class, $select);
        $select = $select->query;
        self::assertInstanceOf(Select::class, $select);
        self::assertInstanceOf(SelectExpression::class, $select->items[0]);
        self::assertInstanceOf(DerivedTable::class, $select->from);
        self::assertInstanceOf(Select::class, $select->from->query);
        self::assertInstanceOf(SelectExpression::class, $select->from->query->items[0]);
        self::assertNotNull($select->where);
        $projection = $select->items[0]->expression->scalarInformation()->resolution;
        $source = $select->from->query->items[0]->expression->scalarInformation()->resolution;
        $condition = $select->where->scalarInformation()->resolution;
        self::assertInstanceOf(ParameterBinding::class, $projection);
        self::assertInstanceOf(ParameterBinding::class, $source);
        self::assertInstanceOf(ParameterBinding::class, $condition);
        self::assertSame([0, 1, 2], [$projection->position, $source->position, $condition->position]);
    }

    public function testPositionRejectsANegativeInputSlot(): void
    {
        $this->expectExceptionMessage('A parameter binding names a non-negative input slot.');
        new ParameterBinding(-1);
    }
}
