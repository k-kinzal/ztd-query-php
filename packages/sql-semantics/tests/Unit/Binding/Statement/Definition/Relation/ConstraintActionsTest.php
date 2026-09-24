<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Relation\ConstraintActions;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ConstraintActions::class)]
#[Medium]
final class ConstraintActionsTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(['ALTER TABLE t ADD CONSTRAINT fk FOREIGN KEY (n) REFERENCES t (id) NOT VALID', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ADD CONSTRAINT "fk" FOREIGN KEY("n") REFERENCES "t"("id") ON DELETE NO ACTION ON UPDATE NO ACTION NOT VALID'])]
    #[TestWith(['ALTER TABLE t ADD CHECK (id > 0) NO INHERIT', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ADD CHECK (("id" > 0)) NO INHERIT'])]
    #[TestWith(['ALTER TABLE t ADD UNIQUE (id)', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ADD UNIQUE("id")'])]
    public function testAddBindsKeysChecksAndForeignKeys(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER TABLE t ADD EXCLUDE USING gist (id WITH =) INCLUDE (n) WITH (fillfactor = 50) USING INDEX TABLESPACE ts WHERE (id > 0)', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ADD EXCLUDE USING "gist"("id" WITH =) INCLUDE("n") WITH ("fillfactor" = 50) USING INDEX TABLESPACE "ts" WHERE (("id" > 0))'])]
    public function testExclusionReadsEveryClause(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER TABLE t ADD EXCLUDE (id WITH =) WITH (fillfactor = 50, deduplicate_items)', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ADD EXCLUDE("id" WITH =) WITH ("fillfactor" = 50, "deduplicate_items")'])]
    public function testDefinitionReadsParametersWithAndWithoutValues(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER TABLE t ADD EXCLUDE (id WITH =) DEFERRABLE INITIALLY DEFERRED', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ADD EXCLUDE("id" WITH =) DEFERRABLE INITIALLY DEFERRED'])]
    #[TestWith(['ALTER TABLE t ALTER CONSTRAINT c INITIALLY IMMEDIATE DEFERRABLE', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ALTER CONSTRAINT "c" DEFERRABLE INITIALLY IMMEDIATE'])]
    public function testCheckingReadsDeferrability(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER TABLE t ALTER CONSTRAINT c NOT DEFERRABLE', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ALTER CONSTRAINT "c"'])]
    public function testAlterReadsTheCheckingTime(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER TABLE t DROP CONSTRAINT c CASCADE', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" DROP CONSTRAINT "c" CASCADE'])]
    public function testDropReadsExistenceAndBehavior(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }

    #[TestWith(['ALTER TABLE t ADD PRIMARY KEY (id) NOT VALID'])]
    public function testAddRejectsNotValidOnAKey(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ConstraintAttribute->message());
        $binder->bind($sql, strict: false);
    }

    #[TestWith(['ALTER TABLE t ALTER CONSTRAINT c NOT DEFERRABLE INITIALLY DEFERRED'])]
    public function testCheckingRejectsDeferredWithoutDeferrable(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ConstraintAttribute->message());
        $binder->bind($sql, strict: false);
    }
}
