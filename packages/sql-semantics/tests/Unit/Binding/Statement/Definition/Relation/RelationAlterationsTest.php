<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Relation\RelationAlterations;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RelationAlterations::class)]
#[Medium]
final class RelationAlterationsTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(['ALTER TABLE IF EXISTS ONLY t SET LOGGED', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE IF EXISTS ONLY "t" SET LOGGED'])]
    #[TestWith(['ALTER VIEW v OWNER TO r', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER VIEW "v" OWNER TO "r"'])]
    #[TestWith(['ALTER SEQUENCE s SET LOGGED', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER SEQUENCE "s" SET LOGGED'])]
    #[TestWith(['ALTER FOREIGN TABLE f * OPTIONS (ADD a \'b\')', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER FOREIGN TABLE "f" OPTIONS(ADD "a" \'b\')'])]
    public function testBindBindsEveryRelationClass(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['ALTER TABLE t ALTER n SET DEFAULT id + 1', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ALTER COLUMN "n" SET DEFAULT("id" + 1)'])]
    public function testScopeResolvesTablesForColumnExpressions(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['ALTER MATERIALIZED VIEW ALL IN TABLESPACE a SET TABLESPACE b', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\MoveTablespaceRelationsStatement::class, 'ALTER MATERIALIZED VIEW ALL IN TABLESPACE "a" SET TABLESPACE "b"'])]
    #[TestWith(['ALTER TABLE ALL IN TABLESPACE a OWNED BY r SET TABLESPACE b NOWAIT', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\MoveTablespaceRelationsStatement::class, 'ALTER TABLE ALL IN TABLESPACE "a" OWNED BY "r" SET TABLESPACE "b" NOWAIT'])]
    public function testMoveReadsSourceDestinationAndOwners(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(['ALTER INDEX ix RESET (fillfactor, x.y)', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER INDEX "ix" RESET("fillfactor", "x"."y")'])]
    public function testParameterNamesReadsNamesWithoutValues(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    #[TestWith(['ALTER TABLE t RESET (fillfactor = 1)'])]
    public function testParameterNamesRejectsAValue(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ResetParameterValue->message());
        $binder->bind($sql, strict: false);
    }

    #[TestWith(['ALTER TABLE t ADD CONSTRAINT c PRIMARY KEY (id)', 'ALTER TABLE "t" ADD CONSTRAINT "c" PRIMARY KEY("id")'])]
    #[TestWith(['ALTER TABLE t ADD CONSTRAINT c CHECK (id > 0)', 'ALTER TABLE "t" ADD CONSTRAINT "c" CHECK (("id" > 0))'])]
    #[TestWith(['ALTER FOREIGN TABLE t ADD CONSTRAINT c CHECK (id > 0)', 'ALTER FOREIGN TABLE "t" ADD CONSTRAINT "c" CHECK (("id" > 0))'])]
    #[TestWith(['ALTER INDEX ix SET (fillfactor = 70)', 'ALTER INDEX "ix" SET ("fillfactor" = 70)'])]
    #[TestWith(['ALTER TABLE t RESET (toast.autovacuum_enabled)', 'ALTER TABLE "t" RESET("toast"."autovacuum_enabled")'])]
    #[TestWith(['ALTER TABLE ALL IN TABLESPACE a OWNED BY r SET TABLESPACE b NOWAIT', 'ALTER TABLE ALL IN TABLESPACE "a" OWNED BY "r" SET TABLESPACE "b" NOWAIT'])]
    public function testBindResolvesTablesAndLeavesOtherRelationsUnresolved(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        self::assertSame($expected, $binder->bind($sql)->toString());
    }

    public function testBindRejectsAKeyOnAForeignTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage('Foreign tables accept only NOT NULL and CHECK constraints.');
        $binder->bind('ALTER FOREIGN TABLE t ADD CONSTRAINT c PRIMARY KEY (id)');
    }
}
