<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query\RowsFrom;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\RowsFrom\RowsFromBinder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RowsFromBinder::class)]
#[Medium]
final class RowsFromBinderTest extends TestCase
{
    #[TestWith(['SELECT * FROM ROWS FROM (f(1), g(2)) WITH ORDINALITY', 'SELECT "f"."f" AS "f", "f"."g" AS "g", "f"."ordinality" AS "ordinality" FROM ROWS FROM("f"(1), "g"(2)) WITH ORDINALITY'])]
    #[TestWith(['SELECT * FROM f(1) AS x(a int, b text)', 'SELECT "x"."a" AS "a", "x"."b" AS "b" FROM ROWS FROM("f"(1) AS ("a" integer, "b" text)) AS "x"'])]
    #[TestWith(['SELECT n FROM generate_series(1, 2) WITH ORDINALITY AS g(v, n)', 'SELECT "n" AS "n" FROM ROWS FROM("generate_series"(1, 2)) WITH ORDINALITY AS "g"("v", "n")'])]
    #[TestWith(['SELECT * FROM t, ROWS FROM (f(t.a)) AS r', 'SELECT "t"."a" AS "a", "r"."f" AS "f" FROM "public"."t" CROSS JOIN ROWS FROM("f"("t"."a")) AS "r"'])]
    #[TestWith(['SELECT ordinality FROM unnest(ARRAY[1], ARRAY[2]) WITH ORDINALITY', 'SELECT "ordinality" AS "ordinality" FROM ROWS FROM("unnest"(ARRAY[1]), "unnest"(ARRAY[2])) WITH ORDINALITY'])]
    #[TestWith(['SELECT * FROM unnest(ARRAY[1], ARRAY[2]) AS u(x, y)', 'SELECT "u"."x" AS "x", "u"."y" AS "y" FROM ROWS FROM("unnest"(ARRAY[1]), "unnest"(ARRAY[2])) AS "u"("x", "y")'])]
    public function testBindReadsFunctionTables(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT)'));
        $statement = $binder->bind($sql);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    #[TestWith(['SELECT * FROM ROWS FROM (f(), g()) AS x(a int)'])]
    #[TestWith(['SELECT * FROM f() WITH ORDINALITY AS x(a int)'])]
    #[TestWith(['SELECT * FROM ROWS FROM (f() AS (a int)) AS x(b int)'])]
    #[TestWith(['SELECT * FROM ROWS FROM (f() AS (a int, a text))'])]
    #[TestWith(['SELECT * FROM ROWS FROM (f() AS (a int)) AS x(p, q)'])]
    public function testBindRejectsImpossibleColumnLists(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::FunctionTableColumns->message());
        $binder->bind($sql);
    }

    public function testDefinitionsReadNamesAndTypes(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('SELECT * FROM ROWS FROM (f() AS (a integer, "B" text))');
        self::assertInstanceOf(\SqlSemantics\Model\ResultStatement::class, $statement);
        self::assertSame(['a', 'B'], array_map(static fn (\SqlSemantics\Model\OutputColumn $column): ?string => $column->name, $statement->resultColumns()));
    }

    public function testExpandedUnnestRecognizesSeveralArrays(): void
    {
        $tree = (new DialectParser(Dialect::PostgreSql))->parse('SELECT * FROM unnest(a, b), unnest(c)');
        $functions = Tree::outer($tree, ['func_table']);
        self::assertTrue(RowsFromBinder::expandedUnnest($functions[0]));
        self::assertFalse(RowsFromBinder::expandedUnnest($functions[1]));
        $named = Tree::outer((new DialectParser(Dialect::PostgreSql))->parse('SELECT * FROM unnest(a, VARIADIC b)'), ['func_table']);
        self::assertFalse(RowsFromBinder::expandedUnnest($named[0]));
    }

    public function testUnnestsWritesOneInvocationPerArray(): void
    {
        $tree = (new DialectParser(Dialect::PostgreSql))->parse('SELECT * FROM unnest(a, b + 1)');
        $items = RowsFromBinder::unnests(Tree::outer($tree, ['func_table'])[0]);
        self::assertSame(['unnest ( a )', 'unnest ( b + 1 )'], array_map(Tree::text(...), $items));
        self::assertSame(['rowsfrom_item', 'rowsfrom_item'], array_map(static fn ($item): string => $item->name, $items));
    }

    public function testFunctionsBindEachInvocation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT * FROM ROWS FROM (f(1), g() AS (a integer))');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\RowsFrom\RowsFromRelation::class, $statement->from);
        self::assertSame([0, 1], array_map(static fn (\SqlSemantics\Model\TableFunction\RowsFrom\RowsFromFunction $function): int => count($function->columns), $statement->from->table->functions));
    }
}
