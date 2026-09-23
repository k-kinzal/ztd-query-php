<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
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

}
