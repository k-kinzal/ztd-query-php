<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Table;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Foreign\PartitionColumn;
use SqlSemantics\Model\Definition\Relation\Foreign\TableTemplate;
use SqlSemantics\Model\Definition\Table\TemplatePlacement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Schema\Table\Persistence;
use SqlSemantics\Schema\Table\PostgreSqlProperties;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Table\PostgreSqlTables;
use SqlSemantics\Type\Nullability;

#[CoversClass(PostgreSqlTables::class)]
#[Medium]
final class PostgreSqlTablesTest extends TestCase
{
    public function testWriteSpellsPartitionsAndTypedTablesAndIgnoresOtherStatements(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE p(id INTEGER)'));
        self::assertSame('CREATE TABLE "c" PARTITION OF "p"("id" WITH OPTIONS NOT NULL) FOR VALUES IN(1)', PostgreSqlTables::write($binder->bind('CREATE TABLE c PARTITION OF p (id WITH OPTIONS NOT NULL) FOR VALUES IN (1)'))?->toString());
        self::assertSame('CREATE TABLE "t" OF "ty"', PostgreSqlTables::write($binder->bind('CREATE TABLE t OF ty'))?->toString());
        self::assertNull(PostgreSqlTables::write($binder->bind('SELECT 1')));
    }

    public function testHeaderSpellsPersistenceAndIfNotExists(): void
    {
        self::assertSame('CREATE TABLE', PostgreSqlTables::header(new PostgreSqlProperties(), false)->toString());
        self::assertSame('CREATE TEMPORARY TABLE IF NOT EXISTS', PostgreSqlTables::header(new PostgreSqlProperties(Persistence::Temporary), true)->toString());
        self::assertSame('CREATE UNLOGGED TABLE', PostgreSqlTables::header(new PostgreSqlProperties(Persistence::Unlogged), false)->toString());
    }

    public function testElementsAreOmittedWhenThereAreNone(): void
    {
        self::assertSame([], PostgreSqlTables::elements([], [], []));
        self::assertSame('("a" WITH OPTIONS NOT NULL)', PostgreSqlTables::elements([new PartitionColumn('a', Nullability::NotNull)], [], [])[0]->toString());
    }

    public function testTemplatesWritesOnlyThoseAtThePosition(): void
    {
        $templates = [new TemplatePlacement(new TableTemplate(new QualifiedName(['s'])), 0), new TemplatePlacement(new TableTemplate(new QualifiedName(['app', 'r'])), 1)];
        self::assertSame(['LIKE "app"."r"'], array_map(static fn ($tree): string => $tree->toString(), PostgreSqlTables::templates($templates, 1)));
        self::assertSame([], PostgreSqlTables::templates($templates, 2));
    }

    #[TestWith(['CREATE TABLE t (LIKE s INCLUDING ALL EXCLUDING COMMENTS, c INT, CONSTRAINT k CHECK (c > 0), EXCLUDE USING gist (c WITH =))', \SqlSemantics\Model\Statement\CreateTableStatement::class, 'CREATE TABLE "public"."t"(LIKE "s" INCLUDING ALL EXCLUDING COMMENTS, "c" integer, CONSTRAINT "k" CHECK (("c" > 0)), EXCLUDE USING "gist"("c" WITH =))'])]
    #[TestWith(['CREATE TABLE u (LIKE s including defaults)', \SqlSemantics\Model\Statement\CreateTableStatement::class, 'CREATE TABLE "public"."u"(LIKE "s" INCLUDING DEFAULTS)'])]
    public function testWriteSpellsTemplatesConstraintsAndExclusions(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE s(a INT, b TEXT)')))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, $statement->toString()]);
    }
}
