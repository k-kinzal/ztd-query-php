<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Mutation\UpdateTableStatement;
use SqlSemantics\Model\Write\Assignment;
use SqlSemantics\Model\Write\Assignment\DefaultAssignment;
use SqlSemantics\Model\Write\Assignment\ScalarAssignment;
use SqlSemantics\Model\Write\Assignment\TupleQueryAssignment;
use SqlSemantics\Model\Write\Assignment\TupleRowAssignment;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Write\Assignments;

#[CoversClass(Assignments::class)]
#[Medium]
final class AssignmentsTest extends TestCase
{
    public function testWriteSeparatesAssignmentsWithCommasInOrder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind('UPDATE t SET n = DEFAULT, id = 2');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertSame('"n" = DEFAULT, "id" = 2', Assignments::write($statement->writes)->toString());
        self::assertSame('UPDATE "public"."t" SET "n" = DEFAULT, "id" = 2', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testAssignmentWritesEachInputFormFromItsOperands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n INT, a INT[])'));
        $statement = $binder->bind('UPDATE t SET n = DEFAULT, id = 1, (id, n) = ROW(1, DEFAULT), (a[1], n) = (SELECT 1, 2)');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertSame([DefaultAssignment::class, ScalarAssignment::class, TupleRowAssignment::class, TupleQueryAssignment::class], array_map(static fn (Assignment $assignment): string => $assignment::class, $statement->writes));
        self::assertSame(['"n" = DEFAULT', '"id" = 1', '("id", "n") = ROW(1, DEFAULT)', '("a"[1], "n") = (SELECT 1, 2)'], array_map(static fn (Assignment $assignment): string => Assignments::assignment($assignment)->toString(), $statement->writes));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testAssignmentKeepsAOneElementTupleDistinctFromAScalar(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(n INT)'));
        $statement = $binder->bind('UPDATE t SET (n) = ROW(1)');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertInstanceOf(TupleRowAssignment::class, $statement->writes[0]);
        self::assertSame('("n") = ROW(1)', Assignments::assignment($statement->writes[0])->toString());
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(UpdateTableStatement::class, $rebound);
        self::assertInstanceOf(TupleRowAssignment::class, $rebound->writes[0]);
    }

    public function testAssignmentWritesMySqlAssignmentsWithBacktickQuoting(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n INT)')))->bind('UPDATE t SET n = DEFAULT, id = id + 1');
        self::assertInstanceOf(UpdateTableStatement::class, $statement);
        self::assertSame('`n` = DEFAULT, `id` = (`id` + 1)', Assignments::write($statement->writes)->toString());
    }
}
