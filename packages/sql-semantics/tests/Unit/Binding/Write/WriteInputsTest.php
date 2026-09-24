<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Write\WriteInputs;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(WriteInputs::class)]
#[Medium]
final class WriteInputsTest extends TestCase
{
    public function testValueKeepsDefaultSlotsApartFromBoundExpressions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('INSERT INTO t VALUES (DEFAULT, 1)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $statement);
        self::assertSame(\SqlSemantics\Model\Write\DefaultSource::Column, $statement->rows[0][0]);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $statement->rows[0][1]);
        self::assertSame('1', $statement->rows[0][1]->text);
        self::assertSame('INSERT INTO "public"."t" VALUES (DEFAULT, 1)', $statement->toString());
    }

    public function testTupleBindsImplicitAndExplicitRowsWithDefaultSlots(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)'));
        $implicit = $binder->bind('UPDATE t SET (a, b) = (1, DEFAULT)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateTableStatement::class, $implicit);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\TupleRowAssignment::class, $implicit->writes[0]);
        self::assertSame(Dialect::PostgreSql, $implicit->writes[0]->row->dialect);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $implicit->writes[0]->row->items[0]);
        self::assertSame(\SqlSemantics\Model\Write\DefaultSource::Column, $implicit->writes[0]->row->items[1]);
        $explicit = $binder->bind('UPDATE t SET (a, b) = ROW(DEFAULT, 2)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateTableStatement::class, $explicit);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\TupleRowAssignment::class, $explicit->writes[0]);
        self::assertSame(\SqlSemantics\Model\Write\DefaultSource::Column, $explicit->writes[0]->row->items[0]);
        self::assertSame('UPDATE "public"."t" SET ("a", "b") = ROW(DEFAULT, 2)', $explicit->toString());
    }

    public function testTupleReturnsNullForANonRowValue(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INT, b INT)')))->bind('UPDATE t SET (a, b) = (SELECT 1, 2)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Mutation\UpdateTableStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Write\Assignment\TupleQueryAssignment::class, $statement->writes[0]);
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), [$statement->target]);
        self::assertNull(WriteInputs::tuple($statement->writes[0]->query->origin->source, $scope));
    }

    public function testRowsBindsEveryRowAndChecksTheirWidths(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, b INT)'));
        $statement = $binder->bind('INSERT INTO t VALUES (1, DEFAULT), (3, 4)');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Insert\InsertValuesStatement::class, $statement);
        self::assertCount(2, $statement->rows);
        self::assertSame(\SqlSemantics\Model\Write\DefaultSource::Column, $statement->rows[0][1]);
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::ValuesWidth->message());
        $binder->bind('INSERT INTO t VALUES (1, 2), (3)');
    }
}
