<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Relation\ForeignTables;

#[CoversClass(ForeignTables::class)]
#[Medium]
final class ForeignTablesTest extends TestCase
{
    public function testWriteReturnsNullForUnrelatedRequests(): void
    {
        self::assertNull(ForeignTables::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }

    #[TestWith(['CREATE FOREIGN TABLE f (a integer) SERVER s'])]
    #[TestWith(['CREATE FOREIGN TABLE f PARTITION OF t (id WITH OPTIONS NOT NULL) DEFAULT SERVER s'])]
    public function testWriteProducesTheStatementText(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind($sql, strict: false);
        self::assertSame($statement->toString(), ForeignTables::write($statement)?->toString());
    }

    public function testServerWritesTheServerAndOptions(): void
    {
        self::assertSame('SERVER "s"', implode(' ', array_map(static fn ($tree): string => $tree->toString(), ForeignTables::server('s', []))));
    }

    public function testColumnWritesTheOverridesAfterWithOptions(): void
    {
        self::assertSame('"a" WITH OPTIONS NOT NULL', ForeignTables::column(new Relation\Foreign\PartitionColumn('a', \SqlSemantics\Type\Nullability::NotNull))->toString());
    }

    /**
     * @param list<string> $definitions
     */
    #[DataProvider('providerWriteSpellsEveryForeignTableForm')]
    public function testWriteSpellsEveryForeignTableForm(Dialect $dialect, ?string $version, array $definitions, string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect, grammarVersion: $version))->build(...$definitions)))->bind($sql, strict: false);
        self::assertSame($expected, ForeignTables::write($statement)?->toString());
    }

    /**
     * @return iterable<string, array{Dialect, ?string, list<string>, string, string}>
     */
    public static function providerWriteSpellsEveryForeignTableForm(): iterable
    {
        return [
            'CREATE FOREIGN TABLE f (a int OPTIONS (column_name \'x\') NOT NULL, b text COLLATE "C", c int OPTIONS ... 0' => [Dialect::PostgreSql, null, ['CREATE TABLE p (a int, b text) PARTITION BY LIST (a)'], 'CREATE FOREIGN TABLE f (a int OPTIONS (column_name \'x\') NOT NULL, b text COLLATE "C", c int OPTIONS (k \'v\', k2 \'v2\'), CHECK (a > 0)) SERVER s OPTIONS (table_name \'t\', schema_name \'s\')', 'CREATE FOREIGN TABLE "public"."f"("a" integer OPTIONS("column_name" \'x\') NOT NULL, "b" text COLLATE "C", "c" integer OPTIONS("k" \'v\', "k2" \'v2\'), CHECK (("a" > 0))) SERVER "s" OPTIONS("table_name" \'t\', "schema_name" \'s\')'],
            'CREATE FOREIGN TABLE IF NOT EXISTS s1.f (a int) INHERITS (p, q) SERVER s (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE p (a int, b text) PARTITION BY LIST (a)'], 'CREATE FOREIGN TABLE IF NOT EXISTS s1.f (a int) INHERITS (p, q) SERVER s', 'CREATE FOREIGN TABLE IF NOT EXISTS "s1"."f"("a" integer) INHERITS("p", "q") SERVER "s"'],
            'CREATE FOREIGN TABLE f (LIKE p INCLUDING DEFAULTS EXCLUDING CONSTRAINTS, a int) SERVER s (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE p (a int, b text) PARTITION BY LIST (a)'], 'CREATE FOREIGN TABLE f (LIKE p INCLUDING DEFAULTS EXCLUDING CONSTRAINTS, a int) SERVER s', 'CREATE FOREIGN TABLE "public"."f"("a" integer, LIKE "p" INCLUDING DEFAULTS EXCLUDING CONSTRAINTS) SERVER "s"'],
            'CREATE FOREIGN TABLE f (LIKE p) SERVER s (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE p (a int, b text) PARTITION BY LIST (a)'], 'CREATE FOREIGN TABLE f (LIKE p) SERVER s', 'CREATE FOREIGN TABLE "public"."f"(LIKE "p") SERVER "s"'],
            'CREATE FOREIGN TABLE f () SERVER s (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE p (a int, b text) PARTITION BY LIST (a)'], 'CREATE FOREIGN TABLE f () SERVER s', 'CREATE FOREIGN TABLE "public"."f"() SERVER "s"'],
            'CREATE FOREIGN TABLE f PARTITION OF p FOR VALUES IN (1) SERVER s (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE p (a int, b text) PARTITION BY LIST (a)'], 'CREATE FOREIGN TABLE f PARTITION OF p FOR VALUES IN (1) SERVER s', 'CREATE FOREIGN TABLE "f" PARTITION OF "p" FOR VALUES IN(1) SERVER "s"'],
            'CREATE FOREIGN TABLE IF NOT EXISTS f PARTITION OF p (a WITH OPTIONS NOT NULL DEFAULT 1, b WITH OPTIO... 6' => [Dialect::PostgreSql, null, ['CREATE TABLE p (a int, b text) PARTITION BY LIST (a)'], 'CREATE FOREIGN TABLE IF NOT EXISTS f PARTITION OF p (a WITH OPTIONS NOT NULL DEFAULT 1, b WITH OPTIONS COLLATE "C", CHECK (a > 0), CONSTRAINT ck CHECK (a < 9)) FOR VALUES IN (2) SERVER s OPTIONS (a \'b\')', 'CREATE FOREIGN TABLE IF NOT EXISTS "f" PARTITION OF "p"("a" WITH OPTIONS NOT NULL DEFAULT 1, "b" WITH OPTIONS COLLATE "C", CHECK (("a" > 0)), CONSTRAINT "ck" CHECK (("a" < 9))) FOR VALUES IN(2) SERVER "s" OPTIONS("a" \'b\')'],
            'CREATE FOREIGN TABLE f PARTITION OF p (a WITH OPTIONS CHECK (a > 1) NOT NULL) DEFAULT SERVER s (PostgreSql)' => [Dialect::PostgreSql, null, ['CREATE TABLE p (a int, b text) PARTITION BY LIST (a)'], 'CREATE FOREIGN TABLE f PARTITION OF p (a WITH OPTIONS CHECK (a > 1) NOT NULL) DEFAULT SERVER s', 'CREATE FOREIGN TABLE "f" PARTITION OF "p"("a" WITH OPTIONS NOT NULL CHECK (("a" > 1))) DEFAULT SERVER "s"'],
        ];
    }

    #[TestWith(['CREATE FOREIGN TABLE t (a int, b int CHECK (b > a)) SERVER s', 'CREATE FOREIGN TABLE "public"."t"("a" integer, "b" integer, CHECK (("b" > "a"))) SERVER "s"', false])]
    #[TestWith(['CREATE FOREIGN TABLE t (a int, b int CHECK (b > 0) NO INHERIT) SERVER s', 'CREATE FOREIGN TABLE "public"."t"("a" integer, "b" integer, CHECK (("b" > 0)) NO INHERIT) SERVER "s"', true])]
    public function testWriteKeepsAColumnCheckInheritableUnlessWrittenOtherwise(string $sql, string $expected, bool $noInherit): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\CreateForeignTableStatement::class, $statement);
        $check = $statement->definition->table->constraints[0];
        self::assertInstanceOf(\SqlSemantics\Schema\Constraint\Check::class, $check);
        self::assertSame($noInherit, $check->noInherit);
        self::assertSame($expected, ForeignTables::write($statement)?->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }
}
