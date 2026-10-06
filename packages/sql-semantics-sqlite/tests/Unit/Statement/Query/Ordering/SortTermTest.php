<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\ColumnUse;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Operator\Binary;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\NullsOrder;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;

#[CoversClass(SortTerm::class)]
#[Medium]
final class SortTermTest extends TestCase
{
    public function testRenderWritesTheDirectionAndTheNullPlacement(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('select a from t order by a + 1 asc nulls first, b desc, a nulls last, b');

        self::assertInstanceOf(Select::class, $query->statement);
        $terms = $query->statement->orderBy;
        self::assertInstanceOf(Binary::class, $terms[0]->expression);
        self::assertSame(SortDirection::Ascending, $terms[0]->direction);
        self::assertSame(NullsOrder::First, $terms[0]->nulls);
        self::assertSame(SortDirection::Descending, $terms[1]->direction);
        self::assertNull($terms[1]->nulls);
        self::assertNull($terms[2]->direction);
        self::assertSame(NullsOrder::Last, $terms[2]->nulls);
        self::assertNull($terms[3]->direction);
        self::assertNull($terms[3]->nulls);
        self::assertSame('SELECT a FROM t ORDER BY a + 1 ASC NULLS FIRST, b DESC, a NULLS LAST, b', $query->toString());
    }

    public function testRenderWritesANewlyBuiltTerm(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $term = new SortTerm(new ColumnUse(new Name('a')), SortDirection::Descending, NullsOrder::Last);
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new ColumnUse(new Name('a')))], new TableInput(new QualifiedName(new Name('t'))), null, [], null, [], [$term]));

        self::assertSame('SELECT a FROM t ORDER BY a DESC NULLS LAST', $operation->toString());
    }

    public function testRenderWritesTheOrderingOfAnAggregateArgument(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('select group_concat(a order by b desc, a) AS c1 from t');

        self::assertSame('SELECT group_concat(a ORDER BY b DESC, a) AS c1 FROM t', $query->toString());
    }
}
