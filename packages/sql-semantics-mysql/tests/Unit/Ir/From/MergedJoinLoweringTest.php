<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\MergedJoinLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\MySql\Ir\Predicate\LogicalPredicate;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(MergedJoinLowering::class)]
#[Medium]
final class MergedJoinLoweringTest extends TestCase
{
    public function testPredicateUsesTwoDifferentOccurrencesInUsingOrder(): void
    {
        $mysql = new Inspection();
        $left = $mysql->inspect('CREATE TABLE app.first_input (Id INT, lane INT)');
        $right = $mysql->inspect('CREATE TABLE app.second_input (lane INT, id INT)');
        $query = $mysql->inspect('SELECT 1 FROM app.first_input JOIN app.second_input USING (lane, id)', [$left, $right]);
        self::assertInstanceOf(Select::class, $query->statement);
        $join = $query->statement->from;
        self::assertInstanceOf(JoinedTable::class, $join);
        $bound = $query->binding();
        $a = new SourceBinding($bound, $join->left);
        $b = new SourceBinding($bound, $join->right);

        $on = (new MergedJoinLowering())->predicate($join, $a, $b);

        self::assertInstanceOf(LogicalPredicate::class, $on);
        self::assertInstanceOf(IntegerComparison::class, $on->left);
        self::assertEquals(new ColumnReference(0, 1), $on->left->right);
        self::assertInstanceOf(IntegerComparison::class, $on->right);
        self::assertEquals(new ColumnReference(1, 0), $on->right->left);
        self::assertSame('lane', $a->source()?->columns[0]->value);
        self::assertSame('id', $b->source()?->columns[1]->value);
    }
}
