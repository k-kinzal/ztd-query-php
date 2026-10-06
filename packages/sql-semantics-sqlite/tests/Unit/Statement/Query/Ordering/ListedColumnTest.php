<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Ordering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\ListedColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\CommonTable;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;

#[CoversClass(ListedColumn::class)]
#[Medium]
final class ListedColumnTest extends TestCase
{
    public function testRenderWritesTheNameTheCollationAndTheDirection(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('with c(x, "y z" collate nocase desc, w asc) as (select 1, 2, 3) select * from c');

        self::assertInstanceOf(WithQuery::class, $query->statement);
        $columns = $query->statement->with->tables[0]->columns;
        self::assertSame('x', $columns[0]->name->value);
        self::assertNull($columns[0]->collation);
        self::assertNull($columns[0]->direction);
        self::assertSame('y z', $columns[1]->name->value);
        self::assertSame('nocase', $columns[1]->collation?->value);
        self::assertSame(SortDirection::Descending, $columns[1]->direction);
        self::assertSame(SortDirection::Ascending, $columns[2]->direction);
        self::assertSame('WITH c (x, `y z` COLLATE nocase DESC, w ASC) AS (SELECT 1, 2, 3) SELECT * FROM c', $query->toString());
        self::assertSame(MisuseRule::DecoratedColumnName->value, $query->facts->diagnostics[0]->message());
    }

    public function testRenderWritesANewlyBuiltName(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = new CommonTable(new Name('c'), new Select([new ResultColumn(new IntegerLiteral('1'))]), [new ListedColumn(new Name('x'))]);
        $operation = new Operation($semantics->context(), new WithQuery(new WithClause([$table]), new Select([new Star()], new TableInput(new QualifiedName(new Name('c'))))));

        self::assertSame('WITH c (x) AS (SELECT 1) SELECT * FROM c', $operation->toString());
        self::assertSame('x', $operation->field(0)->name?->value);
        self::assertSame([], $operation->facts->diagnostics);
    }
}
