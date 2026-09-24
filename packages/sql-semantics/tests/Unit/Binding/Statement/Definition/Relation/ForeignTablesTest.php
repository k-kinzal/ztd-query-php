<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Relation\ForeignTables;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ForeignTables::class)]
#[Medium]
final class ForeignTablesTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(['CREATE FOREIGN TABLE IF NOT EXISTS app.f (a integer OPTIONS (x \'y\') NOT NULL, b text) INHERITS (t) SERVER s OPTIONS (o \'p\')', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\CreateForeignTableStatement::class, 'CREATE FOREIGN TABLE IF NOT EXISTS "app"."f"("a" integer OPTIONS("x" \'y\') NOT NULL, "b" text) INHERITS("t") SERVER "s" OPTIONS("o" \'p\')'])]
    public function testBindBindsOwnColumnsAndParents(string $sql, string $class, string $expected): void
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
    #[TestWith(['CREATE FOREIGN TABLE f (LIKE t INCLUDING ALL EXCLUDING STORAGE) SERVER s', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\CreateForeignTableStatement::class, 'CREATE FOREIGN TABLE "public"."f"(LIKE "t" INCLUDING ALL EXCLUDING STORAGE) SERVER "s"'])]
    public function testTemplateReadsSelections(string $sql, string $class, string $expected): void
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
    #[TestWith(['CREATE FOREIGN TABLE IF NOT EXISTS f PARTITION OF t FOR VALUES FROM (1) TO (10) SERVER s', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\CreateForeignPartitionStatement::class, 'CREATE FOREIGN TABLE IF NOT EXISTS "f" PARTITION OF "t" FOR VALUES FROM(1) TO(10) SERVER "s"'])]
    public function testPartitionReadsTheParentAndBound(string $sql, string $class, string $expected): void
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
    #[TestWith(['CREATE FOREIGN TABLE f PARTITION OF t (CONSTRAINT c CHECK (n > 0)) DEFAULT SERVER s', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\CreateForeignPartitionStatement::class, 'CREATE FOREIGN TABLE "f" PARTITION OF "t"(CONSTRAINT "c" CHECK (("n" > 0))) DEFAULT SERVER "s"'])]
    public function testConstraintBindsTableConstraintsOfAPartition(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }
}
