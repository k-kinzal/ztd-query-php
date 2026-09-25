<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query\RowsFrom;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
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

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindExpandsOnlyUnqualifiedPositionalUnnest')]
    public function testBindExpandsOnlyUnqualifiedPositionalUnnest(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindExpandsOnlyUnqualifiedPositionalUnnest(): iterable
    {
        return [
            'SELECT * FROM UNNEST(ARRAY[1], ARRAY[2]) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int[], b text[])'], 'SELECT * FROM UNNEST(ARRAY[1], ARRAY[2])', 'SELECT "unnest".* FROM ROWS FROM("unnest"(ARRAY[1]), "unnest"(ARRAY[2]))'],
            'SELECT * FROM public.unnest(ARRAY[1], ARRAY[2]) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int[], b text[])'], 'SELECT * FROM public.unnest(ARRAY[1], ARRAY[2])', 'SELECT "public.unnest"."public.unnest" AS "public.unnest" FROM "public"."unnest"(ARRAY[1], ARRAY[2]) AS "public.unnest"'],
            'SELECT * FROM unnest(ARRAY[1]) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int[], b text[])'], 'SELECT * FROM unnest(ARRAY[1])', 'SELECT "unnest"."unnest" AS "unnest" FROM "unnest"(ARRAY[1]) AS "unnest"'],
            'SELECT * FROM generate_series(1, 2) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int[], b text[])'], 'SELECT * FROM generate_series(1, 2)', 'SELECT "generate_series"."generate_series" AS "generate_series" FROM "generate_series"(1, 2) AS "generate_series"'],
            'SELECT * FROM generate_series(1, 2) AS g (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int[], b text[])'], 'SELECT * FROM generate_series(1, 2) AS g', 'SELECT "g"."g" AS "g" FROM "generate_series"(1, 2) AS "g"'],
            'SELECT * FROM unnest(ARRAY[1], VARIADIC ARRAY[2]) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int[], b text[])'], 'SELECT * FROM unnest(ARRAY[1], VARIADIC ARRAY[2])', 'SELECT "unnest"."unnest" AS "unnest" FROM "unnest"(ARRAY[1], VARIADIC ARRAY[2]) AS "unnest"'],
            'SELECT * FROM unnest(ARRAY[1], x => ARRAY[2]) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int[], b text[])'], 'SELECT * FROM unnest(ARRAY[1], x => ARRAY[2])', 'SELECT "unnest"."unnest" AS "unnest" FROM "unnest"(ARRAY[1], "x" => ARRAY[2]) AS "unnest"'],
            'SELECT * FROM t, unnest(t.a, t.b) AS u(x, y) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int[], b text[])'], 'SELECT * FROM t, unnest(t.a, t.b) AS u(x, y)', 'SELECT "t"."a" AS "a", "t"."b" AS "b", "u"."x" AS "x", "u"."y" AS "y" FROM "public"."t" CROSS JOIN ROWS FROM("unnest"("t"."a"), "unnest"("t"."b")) AS "u"("x", "y")'],
            'SELECT * FROM t, UnNest(t.a, t.b) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int[], b text[])'], 'SELECT * FROM t, UnNest(t.a, t.b)', 'SELECT "t"."a" AS "a", "t"."b" AS "b", "unnest".* FROM "public"."t" CROSS JOIN ROWS FROM("unnest"("t"."a"), "unnest"("t"."b"))'],
            'SELECT * FROM unnest(ARRAY[1], ARRAY[\'a\'], ARRAY[true]) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int[], b text[])'], 'SELECT * FROM unnest(ARRAY[1], ARRAY[\'a\'], ARRAY[true])', 'SELECT "unnest".* FROM ROWS FROM("unnest"(ARRAY[1]), "unnest"(ARRAY[\'a\']), "unnest"(ARRAY[true]))'],
            'SELECT * FROM lower(\'a\') (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int[], b text[])'], 'SELECT * FROM lower(\'a\')', 'SELECT "lower"."lower" AS "lower" FROM "lower"(\'a\') AS "lower"'],
            'SELECT * FROM lower(\'a\') WITH ORDINALITY (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t (a int[], b text[])'], 'SELECT * FROM lower(\'a\') WITH ORDINALITY', 'SELECT "lower"."lower" AS "lower", "lower"."ordinality" AS "ordinality" FROM ROWS FROM("lower"(\'a\')) WITH ORDINALITY'],
        ];
    }
}
