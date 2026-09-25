<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected, strict: false)));
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
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected, strict: false)));
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerReadRecognizesLowercaseOnly')]
    public function testReadRecognizesLowercaseOnly(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerReadRecognizesLowercaseOnly(): iterable
    {
        return [
            'alter table only t rename to u (PostgreSql)' => [Dialect::PostgreSql, null, [], 'alter table only t rename to u', 'ALTER TABLE ONLY "t" RENAME TO "u"'],
            'ALTER TABLE t RENAME TO u (PostgreSql)' => [Dialect::PostgreSql, null, [], 'ALTER TABLE t RENAME TO u', 'ALTER TABLE "t" RENAME TO "u"'],
            'alter table if exists only t rename column a to b (PostgreSql)' => [Dialect::PostgreSql, null, [], 'alter table if exists only t rename column a to b', 'ALTER TABLE IF EXISTS ONLY "t" RENAME COLUMN "a" TO "b"'],
            'alter table t* rename to u (PostgreSql)' => [Dialect::PostgreSql, null, [], 'alter table t* rename to u', 'ALTER TABLE "t" RENAME TO "u"'],
        ];
    }
}
