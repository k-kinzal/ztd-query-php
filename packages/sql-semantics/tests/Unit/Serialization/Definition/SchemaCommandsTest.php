<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\AddColumnStatement;
use SqlSemantics\Model\Statement\Definition\CreateTableAsStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\SchemaCommands;

#[CoversClass(SchemaCommands::class)]
#[Medium]
final class SchemaCommandsTest extends TestCase
{
    #[TestWith(['ALTER TABLE t ADD COLUMN b INT NOT NULL'])]
    #[TestWith(['ALTER TABLE t RENAME TO s'])]
    #[TestWith(['ALTER TABLE t RENAME COLUMN a TO c'])]
    #[TestWith(['ALTER TABLE t DROP COLUMN a'])]
    #[TestWith(['DROP TABLE IF EXISTS t'])]
    #[TestWith(['DROP VIEW v'])]
    #[TestWith(['DROP INDEX IF EXISTS i'])]
    #[TestWith(['DROP TRIGGER IF EXISTS tr'])]
    #[TestWith(['CREATE TEMP TABLE u AS SELECT a FROM t'])]
    public function testWriteRetainsTheConcreteRequestAndOperands(string $sql): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (a INT)'));
        $statement = $binder->bind($sql);
        $rebound = $binder->bind($statement->toString());
        self::assertSame($statement::class, $rebound::class);
        self::assertSame($statement->toString(), $rebound->toString());
    }

    public function testDropWritesSelectionExistenceAndDependencyPolicy(): void
    {
        $tree = SchemaCommands::drop('MATERIALIZED VIEW', [new QualifiedName(['a']), new QualifiedName(['s', 'b'])], true, DropBehavior::Cascade, Dialect::PostgreSql);
        self::assertSame('DROP MATERIALIZED VIEW IF EXISTS "a", "s"."b" CASCADE', $tree->toString());
        self::assertSame('DROP TABLE `t`', SchemaCommands::drop('TABLE', [new QualifiedName(['t'])], false, DropBehavior::Default, Dialect::MySql)->toString());
    }

    public function testAddColumnWritesTheColumnAndItsConstraints(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build('CREATE TABLE t (a INT)')))->bind('ALTER TABLE t ADD COLUMN b INT NOT NULL');
        self::assertInstanceOf(AddColumnStatement::class, $statement);
        self::assertSame('ALTER TABLE "t" ADD COLUMN "b" "int" NOT NULL', SchemaCommands::addColumn($statement)->toString());
    }

    public function testTableAsWritesPersistenceStorageAndPopulation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t (a INT)')))->bind('CREATE UNLOGGED TABLE u (x) WITH (fillfactor = 70) AS SELECT a FROM t WITH NO DATA');
        self::assertInstanceOf(CreateTableAsStatement::class, $statement);
        self::assertSame('CREATE UNLOGGED TABLE "u"("x") WITH ("fillfactor" = 70) AS SELECT "a" AS "a" FROM "public"."t" WITH NO DATA', SchemaCommands::tableAs($statement)->toString());
    }

    /**
     * @return list<array{Dialect, ?string, string, mixed}>
     */
    public static function providerWriteSpellsTemporaryAndColumnCommands(): array
    {
        return [
            [Dialect::MySql, null, 'DROP TEMPORARY TABLE t', [\SqlSemantics\Model\Statement\Definition\DropTableStatement::class, 'DROP TEMPORARY TABLE `t`']],
            [Dialect::Sqlite, null, 'ALTER TABLE t DROP COLUMN a', [\SqlSemantics\Model\Statement\Definition\DropColumnStatement::class, 'ALTER TABLE "t" DROP COLUMN "a"']],
            [Dialect::Sqlite, null, 'ALTER TABLE t ADD COLUMN b INT CHECK (b > 0) NOT NULL DEFAULT 1', [AddColumnStatement::class, 'ALTER TABLE "t" ADD COLUMN "b" "int" NOT NULL DEFAULT 1 CHECK (("b" > 0))']],
            [Dialect::PostgreSql, null, 'CREATE UNLOGGED TABLE x AS SELECT 1 AS a', [CreateTableAsStatement::class, 'CREATE UNLOGGED TABLE "x" AS SELECT 1 AS "a"']],
            [Dialect::PostgreSql, null, 'CREATE TEMPORARY TABLE x AS SELECT 1 AS a', [CreateTableAsStatement::class, 'CREATE TEMPORARY TABLE "x" AS SELECT 1 AS "a"']],
            [Dialect::PostgreSql, null, 'CREATE TABLE x AS SELECT 1 AS a', [CreateTableAsStatement::class, 'CREATE TABLE "x" AS SELECT 1 AS "a"']],
            [Dialect::MySql, null, 'CREATE TEMPORARY TABLE x AS SELECT 1 AS a', [CreateTableAsStatement::class, 'CREATE TEMPORARY TABLE `x` AS SELECT 1 AS `a`']],
            [Dialect::Sqlite, null, 'CREATE TEMP TABLE x AS SELECT 1 AS a', [CreateTableAsStatement::class, 'CREATE TEMPORARY TABLE "x" AS SELECT 1 AS "a"']],
            [Dialect::MySql, null, 'CREATE TABLE x AS SELECT 1 AS a', [CreateTableAsStatement::class, 'CREATE TABLE `x` AS SELECT 1 AS `a`']],
        ];
    }

    #[DataProvider('providerWriteSpellsTemporaryAndColumnCommands')]
    public function testWriteSpellsTemporaryAndColumnCommands(Dialect $dialect, ?string $version, string $sql, mixed $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build('CREATE TABLE t(a INT, c INT)')))->bind($sql, strict: false);
        self::assertTrue($statement instanceof \SqlSemantics\Model\Statement\Definition\DropTableStatement || $statement instanceof \SqlSemantics\Model\Statement\Definition\DropColumnStatement || $statement instanceof AddColumnStatement || $statement instanceof CreateTableAsStatement);
        self::assertSame($expected, [$statement::class, SchemaCommands::write($statement)->toString()]);
    }
}
