<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\With;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\ListedColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\Star;
use SqlSemantics\Platform\Sqlite\Statement\Query\ValuesClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\CommonTable;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\Materialization;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithClause;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;

#[CoversClass(CommonTable::class)]
#[Medium]
final class CommonTableTest extends TestCase
{
    public function testRenderWritesTheColumnListAndTheHint(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('with c(x, y) as materialized (select 1, 2), d as not materialized (values (3)), e as (select 4) select * from c');

        self::assertInstanceOf(WithQuery::class, $query->statement);
        $tables = $query->statement->with->tables;
        self::assertSame('c', $tables[0]->name->value);
        self::assertSame(['x', 'y'], array_map(static fn (ListedColumn $column): string => $column->name->value, $tables[0]->columns));
        self::assertSame(Materialization::Materialized, $tables[0]->materialization);
        self::assertInstanceOf(Select::class, $tables[0]->query);
        self::assertSame([], $tables[1]->columns);
        self::assertSame(Materialization::NotMaterialized, $tables[1]->materialization);
        self::assertInstanceOf(ValuesClause::class, $tables[1]->query);
        self::assertNull($tables[2]->materialization);
        self::assertSame('WITH c (x, y) AS MATERIALIZED (SELECT 1, 2), d AS NOT MATERIALIZED (VALUES (3)), e AS (SELECT 4) SELECT * FROM c', $query->toString());
    }

    public function testRenderWritesANewlyBuiltTable(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $table = new CommonTable(new Name('c'), new Select([new ResultColumn(new IntegerLiteral('1'))]), [new ListedColumn(new Name('x'))], Materialization::NotMaterialized);
        $operation = new Operation($semantics->context([]), new WithQuery(new WithClause([$table]), new Select([new Star()], new TableInput(new QualifiedName(new Name('c'))))));

        self::assertSame('WITH c (x) AS NOT MATERIALIZED (SELECT 1) SELECT * FROM c', $operation->toString());
        self::assertSame('x', $operation->field(0)->name?->value);
    }
}
