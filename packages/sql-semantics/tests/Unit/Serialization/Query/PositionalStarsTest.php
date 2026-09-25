<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\SemanticException;
use SqlSemantics\Serialization\Query\PositionalStars;

#[CoversClass(PositionalStars::class)]
#[Medium]
final class PositionalStarsTest extends TestCase
{
    /**
     * @param list<int|null> $columns
     */
    #[TestWith(['SELECT * FROM (p CROSS JOIN p AS q) AS j', 'SELECT "j".* FROM("public"."p" CROSS JOIN "public"."p" AS "q") AS "j"', [0, 1, 2, 3]])]
    #[TestWith(['SELECT j.* FROM (p CROSS JOIN p AS q) AS j', 'SELECT "j".* FROM("public"."p" CROSS JOIN "public"."p" AS "q") AS "j"', [0, 1, 2, 3]])]
    #[TestWith(['SELECT * FROM (t JOIN u ON t.id = u.id) AS x', 'SELECT "x".* FROM("public"."t" INNER JOIN "public"."u" ON ("t"."id" = "u"."id")) AS "x"', [0, 1, 2, 3]])]
    #[TestWith(['SELECT * FROM ((p CROSS JOIN p AS q) AS j CROSS JOIN t)', 'SELECT "j".*, "t"."id" AS "id", "t"."a" AS "a" FROM("public"."p" CROSS JOIN "public"."p" AS "q") AS "j" CROSS JOIN "public"."t"', [0, 1, 2, 3, 0, 1]])]
    #[TestWith(['SELECT j.*, 1 AS k, j.* FROM (p CROSS JOIN p AS q) AS j', 'SELECT "j".*, 1 AS "k", "j".* FROM("public"."p" CROSS JOIN "public"."p" AS "q") AS "j"', [0, 1, 2, 3, null, 0, 1, 2, 3]])]
    #[TestWith(['SELECT * FROM (SELECT * FROM (p CROSS JOIN p AS q) AS j) s', 'SELECT "s".* FROM(SELECT "j".* FROM("public"."p" CROSS JOIN "public"."p" AS "q") AS "j") AS "s"', [0, 1, 2, 3]])]
    #[TestWith(['SELECT * FROM (SELECT 1 AS a, 2 AS a) AS s', 'SELECT "s".* FROM(SELECT 1 AS "a", 2 AS "a") AS "s"', [0, 1]])]
    public function testStarExpandsARelationThatRepeatsAColumnNameByPosition(string $sql, string $expected, array $columns): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p (id int, v int); CREATE TABLE t (id int, a int); CREATE TABLE u (id int, b int)'));
        $query = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($query));
        $ordinals = array_map(static fn ($output): ?int => $output->expression->columnBinding()?->column->ordinal, $query->outputs);
        self::assertSame($columns, $ordinals);
        $rebound = $binder->bind($expected);
        self::assertInstanceOf(BoundSelect::class, $rebound);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
        self::assertSame(array_map(static fn ($output): ?string => $output->name, $query->outputs), array_map(static fn ($output): ?string => $output->name, $rebound->outputs));
    }

    #[TestWith(['SELECT id FROM (t JOIN u ON t.id = u.id) AS x'])]
    #[TestWith(['SELECT x.id FROM (t JOIN u ON t.id = u.id) AS x'])]
    #[TestWith(['SELECT j.v FROM (p CROSS JOIN p AS q) AS j'])]
    public function testStarLeavesANamedReferenceToARepeatedColumnAmbiguous(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p (id int, v int); CREATE TABLE t (id int, a int); CREATE TABLE u (id int, b int)'));
        $this->expectException(SemanticException::class);
        $this->expectExceptionMessage('Cannot resolve column unambiguously');
        $binder->bind($sql);
    }

    public function testLengthCountsOnlyTheCompleteExpansionOfARelationThatRepeatsAName(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id int, a int); CREATE TABLE u (id int, b int)'));
        $repeated = $binder->bind('SELECT 1 AS k, * FROM (t JOIN u ON t.id = u.id) AS x');
        self::assertInstanceOf(BoundSelect::class, $repeated);
        self::assertSame(0, PositionalStars::length($repeated->outputs, 0));
        self::assertSame(4, PositionalStars::length($repeated->outputs, 1));
        self::assertSame(0, PositionalStars::length($repeated->outputs, 2));
        self::assertSame(0, PositionalStars::length($repeated->outputs, 4));
        self::assertSame(0, PositionalStars::length($repeated->outputs, 9));
        $distinct = $binder->bind('SELECT * FROM t');
        self::assertInstanceOf(BoundSelect::class, $distinct);
        self::assertSame(0, PositionalStars::length($distinct->outputs, 0));
    }

    #[TestWith([5, 3])]
    #[TestWith([4, 1])]
    public function testLengthRejectsARepeatedColumnOutsideACompleteExpansion(int $kept, int $index): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (id int, a int); CREATE TABLE u (id int, b int)'));
        $query = $binder->bind('SELECT 1 AS k, * FROM (t JOIN u ON t.id = u.id) AS x');
        self::assertInstanceOf(BoundSelect::class, $query);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('A column whose name its relation repeats can only be selected by a star over the complete relation.');
        PositionalStars::length(array_slice($query->outputs, 0, $kept), $index);
    }

    /**
     * @param list<int> $selection
     */
    #[TestWith(['postgresql', 'SELECT * FROM (t JOIN u ON t.id = u.id) AS x', [1, 3], 'SELECT "x"."a" AS "a", "x"."b" AS "b" FROM("public"."t" INNER JOIN "public"."u" ON ("t"."id" = "u"."id")) AS "x"'])]
    #[TestWith(['postgresql', 'SELECT * FROM (t JOIN u ON t.id = u.id) AS x', [3, 1], 'SELECT "x"."b" AS "b", "x"."a" AS "a" FROM("public"."t" INNER JOIN "public"."u" ON ("t"."id" = "u"."id")) AS "x"'])]
    #[TestWith(['postgresql', 'SELECT * FROM (t JOIN u ON t.id = u.id) AS x', [0, 1, 2, 3, 1], 'SELECT "x".*, "x"."a" AS "a" FROM("public"."t" INNER JOIN "public"."u" ON ("t"."id" = "u"."id")) AS "x"'])]
    #[TestWith(['postgresql', 'SELECT j.*, 1 AS k FROM (t JOIN u ON t.id = u.id) AS j', [4, 0, 1, 2, 3], 'SELECT 1 AS "k", "j".* FROM("public"."t" INNER JOIN "public"."u" ON ("t"."id" = "u"."id")) AS "j"'])]
    #[TestWith(['postgresql', 'SELECT * FROM t JOIN u ON t.id = u.id', [2, 0], 'SELECT "u"."id" AS "id", "t"."id" AS "id" FROM "public"."t" INNER JOIN "public"."u" ON ("t"."id" = "u"."id")'])]
    #[TestWith(['sqlite', 'SELECT s.* FROM (SELECT * FROM t, u) s', [3, 1], 'SELECT "s"."b" AS "b", "s"."a" AS "a" FROM(SELECT "t"."id" AS "id", "t"."a" AS "a", "u"."id" AS "id", "u"."b" AS "b" FROM "main"."t" CROSS JOIN "main"."u") AS "s"'])]
    #[TestWith(['mysql', 'SELECT * FROM t JOIN u ON t.id = u.id', [3, 0, 2], 'SELECT `u`.`b` AS `b`, `t`.`id` AS `id`, `u`.`id` AS `id` FROM `t` INNER JOIN `u` ON (`t`.`id` = `u`.`id`)'])]
    #[TestWith(['mysql', 'SELECT u.*, t.* FROM t JOIN u ON t.id = u.id', [3, 2], 'SELECT `t`.`a` AS `a`, `t`.`id` AS `id` FROM `t` INNER JOIN `u` ON (`t`.`id` = `u`.`id`)'])]
    public function testStarKeepsTheOutputListOfATransformation(string $dialect, string $sql, array $selection, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::from($dialect)))->build('CREATE TABLE t (id int, a int); CREATE TABLE u (id int, b int)'));
        $query = $binder->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        $outputs = array_map(static fn (int $ordinal, int $index): OutputColumn => new OutputColumn($ordinal, $query->outputs[$index]->name, $query->outputs[$index]->expression), array_keys($selection), $selection);
        $changed = $query->withOutputs($outputs);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame(array_map(static fn (OutputColumn $output): array => [$output->name, $output->expression->columnBinding()?->column->ordinal], $outputs), array_map(static fn (OutputColumn $output): array => [$output->name, $output->expression->columnBinding()?->column->ordinal], $changed->outputs));
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertInstanceOf(BoundSelect::class, $rebound);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
        self::assertCount(count($selection), $rebound->outputs);
        self::assertNotSame($query, $changed);
        self::assertCount(count($query->outputs), $query->outputs);
    }

    /**
     * @param list<int> $selection
     */
    #[TestWith(['postgresql', 'SELECT j.* FROM (p CROSS JOIN p AS q) AS j', [0, 1, 2]])]
    #[TestWith(['postgresql', 'SELECT j.* FROM (p CROSS JOIN p AS q) AS j', [0, 1, 3, 2]])]
    #[TestWith(['postgresql', 'SELECT j.* FROM (p CROSS JOIN p AS q) AS j', [2, 3]])]
    #[TestWith(['postgresql', 'SELECT * FROM (p CROSS JOIN p AS q) AS j', [1]])]
    #[TestWith(['postgresql', 'SELECT * FROM (SELECT 1 AS a, 2 AS a) AS s', [0]])]
    #[TestWith(['sqlite', 'SELECT s.* FROM (SELECT * FROM p, p AS q) s', [0, 1, 2]])]
    public function testStarRejectsATransformationThatSelectsARepeatedColumnByName(string $dialect, string $sql, array $selection): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::from($dialect)))->build('CREATE TABLE p (id int, v int)')))->bind($sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        $outputs = array_map(static fn (int $ordinal, int $index): OutputColumn => new OutputColumn($ordinal, $query->outputs[$index]->name, $query->outputs[$index]->expression), array_keys($selection), $selection);
        $this->expectException(InvalidStructure::class);
        $query->withOutputs($outputs);
    }

    public function testStarWritesTheQualifierOfTheExpandedColumns(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT * FROM (SELECT 1 AS a, 2 AS b) AS s');
        self::assertInstanceOf(BoundSelect::class, $query);
        $first = $query->outputs[0]->expression;
        self::assertInstanceOf(ColumnReference::class, $first);
        self::assertSame('`s`.*', PositionalStars::star($first, Dialect::MySql)->toString());
    }
}
