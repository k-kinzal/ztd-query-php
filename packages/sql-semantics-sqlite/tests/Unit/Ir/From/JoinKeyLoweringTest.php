<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\From\JoinKeyLowering;
use SqlSemantics\Platform\Sqlite\Ir\From\JoinKind;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(JoinKeyLowering::class)]
#[Small]
final class JoinKeyLoweringTest extends TestCase
{
    public function testPredicateWithRepeatedNamesKeepOneVisibleSlotAndBothResolvedOperands(): void
    {
        $sqlite = new Semantics();
        $context = $sqlite->context([
            'CREATE TABLE first(Key TEXT COLLATE NOCASE, payload TEXT)',
            'CREATE TABLE second(key TEXT COLLATE BINARY, score INT)',
        ]);
        $result = $sqlite->analyze('SELECT * FROM first LEFT JOIN second USING (kEy, KEY)', $context);

        self::assertInstanceOf(Select::class, $result);
        self::assertSame(['Key', 'payload', 'score'], array_map(static fn ($column): string => $column->name->value, $result->columns));
        self::assertSame(JoinKind::Left, $result->joins[0]->kind);
        $condition = $result->joins[0]->on;
        self::assertInstanceOf(Logical::class, $condition);
        self::assertEquals(new Comparison(ComparisonOperator::Equal, new ColumnReference(0, 0), new ColumnReference(1, 1)), $condition->left);
        self::assertEquals($condition->left, $condition->right);
    }
}
