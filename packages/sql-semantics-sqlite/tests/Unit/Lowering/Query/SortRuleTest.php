<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Lowering\Query\SortRule;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\WindowSpec;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\ListedColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\NullsOrder;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\OutputOrdinal;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;

#[CoversClass(SortRule::class)]
#[Medium]
final class SortRuleTest extends TestCase
{
    public function testTermsLowersEachTermWithItsDirectionAndNullPlacement(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT * FROM t ORDER BY a, b DESC NULLS FIRST, c ASC NULLS LAST, d NULLS FIRST');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([[null, null], [SortDirection::Descending, NullsOrder::First], [SortDirection::Ascending, NullsOrder::Last], [null, NullsOrder::First]], array_map(static fn (SortTerm $term): array => [$term->direction, $term->nulls], $operation->statement->orderBy));
        self::assertSame('SELECT * FROM t ORDER BY a, b DESC NULLS FIRST, c ASC NULLS LAST, d NULLS FIRST', $operation->toString());
    }

    public function testTermsTreatsAnIntegerAsAResultColumnPositionOnlyInTheOrderByOfAQuery(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT group_concat(a ORDER BY 1), sum(a) OVER (ORDER BY 1) FROM t ORDER BY 1');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertInstanceOf(OutputOrdinal::class, $operation->statement->orderBy[0]->expression);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $operation->statement->columns[1]);
        $aggregate = $operation->statement->columns[0]->expression;
        $window = $operation->statement->columns[1]->expression;
        self::assertInstanceOf(FunctionCall::class, $aggregate);
        self::assertInstanceOf(FunctionCall::class, $window);
        self::assertInstanceOf(IntegerLiteral::class, $aggregate->order[0]->expression);
        self::assertInstanceOf(WindowSpec::class, $window->over);
        self::assertInstanceOf(IntegerLiteral::class, $window->over->order[0]->expression);
        self::assertSame('SELECT group_concat(a ORDER BY 1), sum(a) OVER (ORDER BY 1) FROM t ORDER BY 1', $operation->toString());
    }

    public function testOrderByIsEmptyWithoutTheClause(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $bare = $semantics->analyze('SELECT a FROM t')->statement;
        $sorted = $semantics->analyze('SELECT a FROM t ORDER BY a, b')->statement;

        self::assertInstanceOf(Select::class, $bare);
        self::assertInstanceOf(Select::class, $sorted);
        self::assertSame([], $bare->orderBy);
        self::assertCount(2, $sorted->orderBy);
    }

    public function testDirectionLowersAscDescOrNone(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT * FROM t ORDER BY a asc, b desc, c');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([SortDirection::Ascending, SortDirection::Descending, null], array_map(static fn (SortTerm $term): ?SortDirection => $term->direction, $operation->statement->orderBy));
        self::assertSame('SELECT * FROM t ORDER BY a ASC, b DESC, c', $operation->toString());
    }

    public function testNullsLowersFirstLastOrNone(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT * FROM t ORDER BY a nulls first, b nulls last, c');

        self::assertInstanceOf(Select::class, $operation->statement);
        self::assertSame([NullsOrder::First, NullsOrder::Last, null], array_map(static fn (SortTerm $term): ?NullsOrder => $term->nulls, $operation->statement->orderBy));
        self::assertSame('SELECT * FROM t ORDER BY a NULLS FIRST, b NULLS LAST, c', $operation->toString());
    }

    public function testColumnsLowersTheNamesOfAColumnListWithCollationAndDirection(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('WITH c (a COLLATE nocase DESC, b ASC, d) AS (SELECT 1, 2, 3) SELECT * FROM c');

        self::assertInstanceOf(WithQuery::class, $operation->statement);
        $columns = $operation->statement->with->tables[0]->columns;
        self::assertSame([['a', 'nocase', SortDirection::Descending], ['b', null, SortDirection::Ascending], ['d', null, null]], array_map(static fn (ListedColumn $column): array => [$column->name->value, $column->collation?->value, $column->direction], $columns));
        self::assertInstanceOf(Misuse::class, $operation->facts->diagnostics[0]);
        self::assertSame(MisuseRule::DecoratedColumnName, $operation->facts->diagnostics[0]->rule);
        self::assertSame('WITH c (a COLLATE nocase DESC, b ASC, d) AS (SELECT 1, 2, 3) SELECT * FROM c', $operation->toString());
    }

    public function testOptionalColumnsGivesNoColumnsWithoutParentheses(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $bare = $semantics->analyze('WITH c AS (SELECT 1) SELECT * FROM c')->statement;
        $listed = $semantics->analyze('WITH c (x, y) AS (SELECT 1, 2) SELECT * FROM c')->statement;

        self::assertInstanceOf(WithQuery::class, $bare);
        self::assertInstanceOf(WithQuery::class, $listed);
        self::assertSame([], $bare->with->tables[0]->columns);
        self::assertSame(['x', 'y'], array_map(static fn (ListedColumn $column): string => $column->name->value, $listed->with->tables[0]->columns));
    }
}
