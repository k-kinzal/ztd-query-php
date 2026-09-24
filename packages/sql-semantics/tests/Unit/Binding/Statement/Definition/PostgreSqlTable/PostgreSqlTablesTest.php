<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\PostgreSqlTable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\PostgreSqlTable\PostgreSqlTables;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\CreatePartitionStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Table\CreateTypedTableStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PostgreSqlTables::class)]
#[Medium]
final class PostgreSqlTablesTest extends TestCase
{
    #[TestWith(['CREATE TABLE c PARTITION OF p FOR VALUES IN (1, 2)', 'CREATE TABLE "c" PARTITION OF "p" FOR VALUES IN(1, 2)'])]
    #[TestWith(['CREATE TABLE c PARTITION OF p FOR VALUES FROM (MINVALUE) TO (10)', 'CREATE TABLE "c" PARTITION OF "p" FOR VALUES FROM(MINVALUE) TO(10)'])]
    #[TestWith(['CREATE TABLE c PARTITION OF p FOR VALUES WITH (MODULUS 4, REMAINDER 1)', 'CREATE TABLE "c" PARTITION OF "p" FOR VALUES WITH(MODULUS 4, REMAINDER 1)'])]
    #[TestWith(['CREATE UNLOGGED TABLE app.c PARTITION OF p (id WITH OPTIONS DEFAULT 3, CONSTRAINT e EXCLUDE (id WITH =)) DEFAULT WITH (fillfactor = 10)', 'CREATE UNLOGGED TABLE "app"."c" PARTITION OF "p"("id" WITH OPTIONS DEFAULT 3, CONSTRAINT "e" EXCLUDE("id" WITH =)) DEFAULT WITH ("fillfactor" = 10)'])]
    public function testBindReadsAPartition(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER)'));
        $statement = $binder->bind($sql);
        self::assertInstanceOf(CreatePartitionStatement::class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testBindReadsATypedTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE TEMP TABLE IF NOT EXISTS t OF s.ty (a WITH OPTIONS NOT NULL) ON COMMIT DELETE ROWS');
        self::assertInstanceOf(CreateTypedTableStatement::class, $statement);
        self::assertSame(['s', 'ty'], $statement->type->parts);
        self::assertSame('CREATE TEMPORARY TABLE IF NOT EXISTS "t" OF "s"."ty"("a" WITH OPTIONS NOT NULL) ON COMMIT DELETE ROWS', $statement->toString());
    }

    public function testBindLeavesAnOrdinaryDeclarationToTheTableBinder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TABLE t (id INTEGER)');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
    }

    public function testBindDiagnosesARepeatedOverride(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ColumnOverride->message());
        $binder->bind('CREATE TABLE c PARTITION OF p (id WITH OPTIONS NOT NULL, id WITH OPTIONS DEFAULT 1) DEFAULT');
    }

    public function testElementsDiagnoseAConstraintOnAnExistingIndex(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ExistingIndexConstraint->message());
        $binder->bind('CREATE TABLE c PARTITION OF p (CONSTRAINT u UNIQUE USING INDEX i) DEFAULT');
    }

    public function testElementsSeparateOverridesConstraintsAndExclusions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER, n INTEGER)')))->bind('CREATE TABLE c PARTITION OF p (id WITH OPTIONS NOT NULL, CHECK (n > 0), EXCLUDE (n WITH =)) DEFAULT');
        self::assertInstanceOf(CreatePartitionStatement::class, $statement);
        self::assertCount(1, $statement->columns);
        self::assertCount(1, $statement->constraints);
        self::assertCount(1, $statement->exclusions);
    }

    public function testPropertiesReadSubPartitioningAgainstTheParentColumns(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER, n INTEGER)')))->bind('CREATE TABLE c PARTITION OF p DEFAULT PARTITION BY RANGE (n) USING heap TABLESPACE fast');
        self::assertInstanceOf(CreatePartitionStatement::class, $statement);
        self::assertSame(['n'], $statement->properties->partitioning?->keys[0]->value->referenceParts());
        self::assertSame('heap', $statement->properties->accessMethod);
        self::assertSame('fast', $statement->properties->tablespace);
    }
}
