<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Definition\Catalog\RelationTargets;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RelationTargets::class)]
#[Medium]
final class RelationTargetsTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(['ALTER FOREIGN TABLE IF EXISTS ONLY f SET SCHEMA app', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\SetRelationSchemaStatement::class, 'ALTER FOREIGN TABLE IF EXISTS ONLY "f" SET SCHEMA "app"'])]
    #[TestWith(['ALTER MATERIALIZED VIEW IF EXISTS mv SET SCHEMA app', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\SetRelationSchemaStatement::class, 'ALTER MATERIALIZED VIEW IF EXISTS "mv" SET SCHEMA "app"'])]
    public function testReadReadsExistenceAndDescendantPolicies(string $sql, string $class, string $expected): void
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
    #[TestWith(['ALTER INDEX IF EXISTS ix RENAME TO iy', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\RenameRelationStatement::class, 'ALTER INDEX IF EXISTS "ix" RENAME TO "iy"'])]
    #[TestWith(['ALTER FOREIGN TABLE f RENAME COLUMN a TO b', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\RenameRelationColumnStatement::class, 'ALTER FOREIGN TABLE "f" RENAME COLUMN "a" TO "b"'])]
    #[TestWith(['ALTER TABLE t RENAME CONSTRAINT a TO b', \SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\RenameTableConstraintStatement::class, 'ALTER TABLE "t" RENAME CONSTRAINT "a" TO "b"'])]
    public function testRenameDistinguishesRelationColumnAndConstraint(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)'));
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected, strict: false)->toString());
    }
}
