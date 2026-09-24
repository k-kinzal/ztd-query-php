<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Relation\AlterRelationStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Relation\ColumnActions;

#[CoversClass(ColumnActions::class)]
#[Medium]
final class ColumnActionsTest extends TestCase
{
    public function testWriteWritesEachColumnChange(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('ALTER TABLE t ALTER id SET STORAGE main', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertSame('ALTER COLUMN "id" SET STORAGE MAIN', ColumnActions::write($statement->actions[0])?->toString());
    }

    public function testWriteReturnsNullForRelationLevelActions(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('ALTER TABLE t SET LOGGED', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertNull(ColumnActions::write($statement->actions[0]));
    }

    public function testAlterPrefixesTheColumn(): void
    {
        self::assertSame('ALTER COLUMN "c" DROP DEFAULT', ColumnActions::alter(\SqlSemantics\Model\Sql\Build::identifier(['c'], Dialect::PostgreSql), [\SqlSemantics\Model\Sql\Build::keyword('DROP DEFAULT')])->toString());
    }

    public function testOptionsWritesStorageOptionsAndIdentityChanges(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind('ALTER TABLE t ALTER id RESET (n_distinct)', strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertSame('ALTER COLUMN "id" RESET("n_distinct")', ColumnActions::options($statement->actions[0])?->toString());
    }

    public function testAddPlacesWrapperOptionsBeforeConstraints(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind("ALTER FOREIGN TABLE t ADD c integer OPTIONS (a 'b') NOT NULL");
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Column\AddColumn::class, $statement->actions[0]);
        self::assertSame('ADD COLUMN "c" integer OPTIONS("a" \'b\') NOT NULL', ColumnActions::add($statement->actions[0])->toString());
    }

    public function testTypeWritesCollationAndConversion(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('ALTER TABLE t ALTER id TYPE text COLLATE "C" USING id::text');
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertInstanceOf(Relation\Column\ColumnTypeChange::class, $statement->actions[0]);
        self::assertSame('TYPE text COLLATE "C" USING CAST("id" AS text)', implode(' ', array_map(static fn ($tree): string => $tree->toString(), ColumnActions::type($statement->actions[0]))));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerWriteWritesTheExactColumnAction(): array
    {
        return [
            ['ALTER TABLE t ALTER id SET DEFAULT 1', 'ALTER COLUMN "id" SET DEFAULT 1'],
            ['ALTER TABLE t ALTER id DROP DEFAULT', 'ALTER COLUMN "id" DROP DEFAULT'],
            ['ALTER TABLE t ALTER id SET NOT NULL', 'ALTER COLUMN "id" SET NOT NULL'],
            ['ALTER TABLE t ALTER id DROP NOT NULL', 'ALTER COLUMN "id" DROP NOT NULL'],
            ['ALTER TABLE t ALTER g SET EXPRESSION AS (n + 1)', 'ALTER COLUMN "g" SET EXPRESSION AS(("n" + 1))'],
            ['ALTER TABLE t ALTER g DROP EXPRESSION', 'ALTER COLUMN "g" DROP EXPRESSION'],
            ['ALTER TABLE t ALTER g DROP EXPRESSION IF EXISTS', 'ALTER COLUMN "g" DROP EXPRESSION IF EXISTS'],
            ['ALTER TABLE t ALTER id SET STATISTICS 100', 'ALTER COLUMN "id" SET STATISTICS 100'],
            ['ALTER TABLE t ALTER id SET STATISTICS -1', 'ALTER COLUMN "id" SET STATISTICS DEFAULT'],
            ['ALTER TABLE t ALTER id SET STATISTICS 0', 'ALTER COLUMN "id" SET STATISTICS 0'],
            ['ALTER INDEX t ALTER 1 SET STATISTICS 5', 'ALTER COLUMN 1 SET STATISTICS 5'],
            ['ALTER TABLE t ALTER id SET COMPRESSION pglz', 'ALTER COLUMN "id" SET COMPRESSION pglz'],
            ['ALTER TABLE t ALTER id TYPE bigint', 'ALTER COLUMN "id" TYPE bigint'],
            ['ALTER TABLE t ALTER id SET (n_distinct = 5)', 'ALTER COLUMN "id" SET ("n_distinct" = 5)'],
            ['ALTER FOREIGN TABLE t ALTER id OPTIONS (ADD a \'b\', DROP c)', 'ALTER COLUMN "id" OPTIONS(ADD "a" \'b\', DROP "c")'],
            ['ALTER TABLE t ALTER id ADD GENERATED ALWAYS AS IDENTITY', 'ALTER COLUMN "id" ADD GENERATED ALWAYS AS IDENTITY'],
            ['ALTER TABLE t ALTER x SET INCREMENT BY 2', 'ALTER COLUMN "x" SET INCREMENT BY 2'],
            ['ALTER TABLE t ALTER x DROP IDENTITY IF EXISTS', 'ALTER COLUMN "x" DROP IDENTITY IF EXISTS'],
            ['ALTER TABLE t ADD COLUMN IF NOT EXISTS z integer NOT NULL DEFAULT 1 CHECK (z > 0)', 'ADD COLUMN IF NOT EXISTS "z" integer NOT NULL DEFAULT 1 CHECK (("z" > 0))'],
            ['ALTER TABLE t ADD COLUMN z integer', 'ADD COLUMN "z" integer'],
            ['ALTER FOREIGN TABLE t ADD c text OPTIONS (a \'b\') COLLATE "C" DEFAULT \'x\' NOT NULL', 'ADD COLUMN "c" text COLLATE "C" OPTIONS("a" \'b\') NOT NULL DEFAULT \'x\''],
            ['ALTER TABLE t DROP COLUMN IF EXISTS n CASCADE', 'DROP COLUMN IF EXISTS "n" CASCADE'],
            ['ALTER TABLE t DROP COLUMN n', 'DROP COLUMN "n"'],
        ];
    }

    #[DataProvider('providerWriteWritesTheExactColumnAction')]
    public function testWriteWritesTheExactColumnAction(string $sql, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER, g INTEGER GENERATED ALWAYS AS (id) STORED, x INTEGER GENERATED ALWAYS AS IDENTITY)')))->bind($sql, strict: false);
        self::assertInstanceOf(AlterRelationStatement::class, $statement);
        self::assertSame($expected, ColumnActions::write($statement->actions[0])?->toString());
    }
}
