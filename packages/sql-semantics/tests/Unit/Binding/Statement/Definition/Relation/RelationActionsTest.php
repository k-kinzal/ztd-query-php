<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Relation\RelationActions;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RelationActions::class)]
#[Medium]
final class RelationActionsTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(['ALTER TABLE t CLUSTER ON ix, OWNER TO r, REPLICA IDENTITY NOTHING, RESET (fillfactor)', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" CLUSTER ON "ix", OWNER TO "r", REPLICA IDENTITY NOTHING, RESET("fillfactor")'])]
    public function testReadClassifiesEveryRelationAction(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER TABLE t ADD n2 integer, ALTER CONSTRAINT c DEFERRABLE, DROP CONSTRAINT IF EXISTS c, VALIDATE CONSTRAINT c', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ADD COLUMN "n2" integer, ALTER CONSTRAINT "c" DEFERRABLE INITIALLY IMMEDIATE, DROP CONSTRAINT IF EXISTS "c", VALIDATE CONSTRAINT "c"'])]
    public function testMemberDelegatesColumnAndConstraintCommands(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER TABLE t INHERIT p, NO INHERIT q, OF ty, NOT OF, FORCE ROW LEVEL SECURITY, NO FORCE ROW LEVEL SECURITY', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" INHERIT "p", NO INHERIT "q", OF "ty", NOT OF, FORCE ROW LEVEL SECURITY, NO FORCE ROW LEVEL SECURITY'])]
    public function testHierarchyReadsInheritanceAndTypedTables(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER TABLE t SET WITHOUT CLUSTER, SET UNLOGGED, SET TABLESPACE ts, SET (fillfactor = 10)', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" SET WITHOUT CLUSTER, SET UNLOGGED, SET TABLESPACE "ts", SET ("fillfactor" = 10)'])]
    public function testSetReadsSetForms(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER MATERIALIZED VIEW mv SET ACCESS METHOD heap2', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER MATERIALIZED VIEW "mv" SET ACCESS METHOD "heap2"'])]
    public function testAccessMethodReadsANameOrDefault(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER TABLE t ENABLE TRIGGER USER, DISABLE RULE r, ENABLE REPLICA TRIGGER tr, DISABLE ROW LEVEL SECURITY', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ENABLE TRIGGER USER, DISABLE RULE "r", ENABLE REPLICA TRIGGER "tr", DISABLE ROW LEVEL SECURITY'])]
    public function testFiringReadsTriggersRulesAndGroups(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER TABLE t REPLICA IDENTITY DEFAULT', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" REPLICA IDENTITY DEFAULT'])]
    #[TestWith(['ALTER TABLE t REPLICA IDENTITY USING INDEX ix', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" REPLICA IDENTITY USING INDEX "ix"'])]
    public function testReplicaIdentityReadsAPolicyOrAnIndex(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected, strict: false)));
    }

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerReadWritesEachRelationAction(): array
    {
        return [
            [Dialect::PostgreSql, null, 'ALTER FOREIGN TABLE t OPTIONS (ADD a \'b\', SET c \'d\', DROP e)', [\SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER FOREIGN TABLE "t" OPTIONS(ADD "a" \'b\', SET "c" \'d\', DROP "e")']],
            [Dialect::PostgreSql, null, 'ALTER TABLE t SET LOGGED, SET UNLOGGED', [\SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" SET LOGGED, SET UNLOGGED']],
            [Dialect::PostgreSql, null, 'ALTER TABLE t SET WITHOUT OIDS, SET WITHOUT CLUSTER', [\SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" SET WITHOUT OIDS, SET WITHOUT CLUSTER']],
            [Dialect::PostgreSql, null, 'ALTER TABLE t ENABLE ROW LEVEL SECURITY, DISABLE ROW LEVEL SECURITY', [\SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ENABLE ROW LEVEL SECURITY, DISABLE ROW LEVEL SECURITY']],
            [Dialect::PostgreSql, null, 'ALTER TABLE t ENABLE ALWAYS TRIGGER x, ENABLE REPLICA TRIGGER y, ENABLE TRIGGER ALL, DISABLE TRIGGER USER', [\SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ENABLE ALWAYS TRIGGER "x", ENABLE REPLICA TRIGGER "y", ENABLE TRIGGER ALL, DISABLE TRIGGER USER']],
            [Dialect::PostgreSql, null, 'alter table t replica identity full', [\SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" REPLICA IDENTITY FULL']],
            [Dialect::PostgreSql, null, 'ALTER TABLE t OF s.typ', [\SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" OF "s"."typ"']],
            [Dialect::PostgreSql, null, 'ALTER TABLE t INHERIT a.s.p', [\SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" INHERIT "a"."s"."p"']],
            [Dialect::PostgreSql, null, 'ALTER TABLE t ADD CONSTRAINT c CHECK (id > 0)', [\SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ADD CONSTRAINT "c" CHECK (("id" > 0))']],
            [Dialect::PostgreSql, null, 'ALTER TABLE t ADD CHECK (id > 0)', [\SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement::class, 'ALTER TABLE "t" ADD CHECK (("id" > 0))']],
        ];
    }

    #[DataProvider('providerReadWritesEachRelationAction')]
    public function testReadWritesEachRelationAction(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind($sql, strict: false);
        self::assertSame($expected, [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }

    #[TestWith(['ALTER TABLE t OF a.s.typ'])]
    #[TestWith(['ALTER TABLE t INHERIT d.a.s.p'])]
    public function testHierarchyRejectsANameWithTooManyComponents(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $binder->bind($sql, strict: false);
    }
}
