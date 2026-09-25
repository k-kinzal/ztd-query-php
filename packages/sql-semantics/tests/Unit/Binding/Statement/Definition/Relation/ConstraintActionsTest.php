<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected, strict: false)));
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
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected, strict: false)));
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
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected, strict: false)));
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
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected, strict: false)));
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
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected, strict: false)));
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
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected, strict: false)));
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

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerBindSpellsLowercaseConstraintCommands')]
    public function testBindSpellsLowercaseConstraintCommands(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerBindSpellsLowercaseConstraintCommands(): iterable
    {
        return [
            'alter table t add constraint c check (id > 0) not valid no inherit (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(id INTEGER, n INTEGER)'], 'alter table t add constraint c check (id > 0) not valid no inherit', 'ALTER TABLE "t" ADD CONSTRAINT "c" CHECK (("id" > 0)) NO INHERIT NOT VALID'],
            'alter table t add constraint u unique (id) deferrable initially deferred (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(id INTEGER, n INTEGER)'], 'alter table t add constraint u unique (id) deferrable initially deferred', 'ALTER TABLE "t" ADD CONSTRAINT "u" UNIQUE("id") DEFERRABLE INITIALLY DEFERRED'],
            'alter table t add constraint x exclude using gist (id with =, n with <>) where (id > 0) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(id INTEGER, n INTEGER)'], 'alter table t add constraint x exclude using gist (id with =, n with <>) where (id > 0)', 'ALTER TABLE "t" ADD CONSTRAINT "x" EXCLUDE USING "gist"("id" WITH =, "n" WITH <>) WHERE (("id" > 0))'],
            'ALTER TABLE t ADD CONSTRAINT x EXCLUDE (id WITH OPERATOR(pg_catalog.=)) DEFERRABLE (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(id INTEGER, n INTEGER)'], 'ALTER TABLE t ADD CONSTRAINT x EXCLUDE (id WITH OPERATOR(pg_catalog.=)) DEFERRABLE', 'ALTER TABLE "t" ADD CONSTRAINT "x" EXCLUDE("id" WITH "pg_catalog".=) DEFERRABLE INITIALLY IMMEDIATE'],
            'ALTER TABLE t ADD EXCLUDE (id WITH =) WITH (fillfactor = \'on\', autosummarize = on, a = off, b = 7.5,... 4' => [Dialect::PostgreSql, null, ['CREATE TABLE t(id INTEGER, n INTEGER)'], 'ALTER TABLE t ADD EXCLUDE (id WITH =) WITH (fillfactor = \'on\', autosummarize = on, a = off, b = 7.5, c = ident)', 'ALTER TABLE "t" ADD EXCLUDE("id" WITH =) WITH ("fillfactor" = \'on\', "autosummarize" = ON, "a" = OFF, "b" = 7.5, "c" = "ident")'],
            'ALTER TABLE t ADD EXCLUDE (id WITH =) WITH (fillfactor = -5) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(id INTEGER, n INTEGER)'], 'ALTER TABLE t ADD EXCLUDE (id WITH =) WITH (fillfactor = -5)', 'ALTER TABLE "t" ADD EXCLUDE("id" WITH =) WITH ("fillfactor" = -5)'],
            'ALTER TABLE t ADD EXCLUDE (id WITH =) WITH (fillfactor = true) (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(id INTEGER, n INTEGER)'], 'ALTER TABLE t ADD EXCLUDE (id WITH =) WITH (fillfactor = true)', 'ALTER TABLE "t" ADD EXCLUDE("id" WITH =) WITH ("fillfactor" = true)'],
            'alter table t add exclude (id with =) initially immediate deferrable (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(id INTEGER, n INTEGER)'], 'alter table t add exclude (id with =) initially immediate deferrable', 'ALTER TABLE "t" ADD EXCLUDE("id" WITH =) DEFERRABLE INITIALLY IMMEDIATE'],
            'alter table t alter constraint c deferrable initially deferred (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(id INTEGER, n INTEGER)'], 'alter table t alter constraint c deferrable initially deferred', 'ALTER TABLE "t" ALTER CONSTRAINT "c" DEFERRABLE INITIALLY DEFERRED'],
            'alter table t alter constraint c not deferrable (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(id INTEGER, n INTEGER)'], 'alter table t alter constraint c not deferrable', 'ALTER TABLE "t" ALTER CONSTRAINT "c"'],
            'alter table t drop constraint c cascade (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(id INTEGER, n INTEGER)'], 'alter table t drop constraint c cascade', 'ALTER TABLE "t" DROP CONSTRAINT "c" CASCADE'],
            'alter table t drop constraint if exists c restrict (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(id INTEGER, n INTEGER)'], 'alter table t drop constraint if exists c restrict', 'ALTER TABLE "t" DROP CONSTRAINT IF EXISTS "c" RESTRICT'],
            'alter table t drop constraint c (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE t(id INTEGER, n INTEGER)'], 'alter table t drop constraint c', 'ALTER TABLE "t" DROP CONSTRAINT "c"'],
        ];
    }

    #[TestWith(['alter table t add unique (id) no inherit'])]
    #[TestWith(['alter table t add exclude (id with =) not deferrable initially deferred'])]
    #[TestWith(['alter table t add exclude (id with =) deferrable not deferrable'])]
    #[TestWith(['alter table t alter constraint c not valid'])]
    public function testAddRejectsContradictoryLowercaseAttributes(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(InvalidSql::class);
        $binder->bind($sql, strict: false);
    }


    public function testExistingAdoptsAUniqueIndex(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER); CREATE UNIQUE INDEX ix ON t(id)'));
        $statement = $binder->bind('ALTER TABLE t ADD CONSTRAINT u UNIQUE USING INDEX ix');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Definition\Relation\Constraint\AddIndexConstraint::class, $statement->actions[0]);
        self::assertSame('ALTER TABLE "t" ADD CONSTRAINT "u" UNIQUE USING INDEX "ix"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
