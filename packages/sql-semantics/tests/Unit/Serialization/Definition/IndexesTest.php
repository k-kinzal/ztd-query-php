<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\CreateIndexStatement;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\Schema\Index\Kind;
use SqlSemantics\Schema\Index\Properties;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Indexes;

#[CoversClass(Indexes::class)]
#[Medium]
final class IndexesTest extends TestCase
{
    public function testInlineWritesEachTableLocalIndexKind(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("CREATE TABLE t (a INT, b INT, INDEX ix USING BTREE (a) COMMENT 'c' KEY_BLOCK_SIZE = 4, FULLTEXT INDEX ft (b), SPATIAL INDEX sp (a))");
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $indexes = $statement->definition->table->indexes;
        self::assertSame("INDEX `ix` USING BTREE(`a`) KEY_BLOCK_SIZE = 4 COMMENT 'c'", Indexes::inline($indexes[0], Dialect::MySql)->toString());
        self::assertSame('FULLTEXT INDEX `ft`(`b`)', Indexes::inline($indexes[1], Dialect::MySql)->toString());
        self::assertSame('SPATIAL INDEX `sp`(`a`)', Indexes::inline($indexes[2], Dialect::MySql)->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(CreateTableStatement::class, $rebound);
        self::assertSame([Kind::Ordinary, Kind::FullText, Kind::Spatial], array_map(static fn ($index): Kind => $index->properties->kind, $rebound->definition->table->indexes));
        self::assertSame($statement->toString(), $rebound->toString());
    }

    public function testKindReturnsTheClassifiedPrefix(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE TABLE t (a INT, INDEX ix (a), FULLTEXT ft (a), SPATIAL sp (a))');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertSame(['', 'FULLTEXT ', 'SPATIAL '], array_map(Indexes::kind(...), $statement->definition->table->indexes));
        $unique = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT)')))->bind('CREATE UNIQUE INDEX ix ON t (id)');
        self::assertInstanceOf(CreateIndexStatement::class, $unique);
        self::assertSame('UNIQUE ', Indexes::kind($unique->index->definition));
    }

    public function testKeysWritesIncludedColumnsNullPolicyAndPredicate(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n INT)'));
        $statement = $binder->bind('CREATE UNIQUE INDEX ix ON t (id DESC NULLS LAST) INCLUDE (n) NULLS NOT DISTINCT WHERE id > 0');
        self::assertInstanceOf(CreateIndexStatement::class, $statement);
        self::assertSame('("id" DESC NULLS LAST) INCLUDE("n") NULLS NOT DISTINCT WHERE ("id" > 0)', Indexes::keys($statement->index->definition, Dialect::PostgreSql)->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(CreateIndexStatement::class, $rebound);
        self::assertSame(['n'], $rebound->index->definition->include);
        self::assertFalse($rebound->index->definition->properties->nullsDistinct);
        self::assertNotNull($rebound->index->definition->predicate);
    }

    public function testOptionsWritesVisibilityStorageAndCommentOptions(): void
    {
        $mysql = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT, n VARCHAR(10))')))->bind("CREATE INDEX ix ON t (n) COMMENT 'c' ENGINE_ATTRIBUTE '{}' SECONDARY_ENGINE_ATTRIBUTE '{}' VISIBLE KEY_BLOCK_SIZE = 8");
        self::assertInstanceOf(CreateIndexStatement::class, $mysql);
        self::assertSame("VISIBLE KEY_BLOCK_SIZE = 8 COMMENT 'c' ENGINE_ATTRIBUTE '{}' SECONDARY_ENGINE_ATTRIBUTE '{}'", Indexes::options($mysql->index->definition->properties, Dialect::MySql)->toString());
        $postgres = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INT, n TEXT)')))->bind('CREATE INDEX ix ON t (id) WITH (fillfactor = 70) TABLESPACE ts');
        self::assertInstanceOf(CreateIndexStatement::class, $postgres);
        self::assertSame('WITH ("fillfactor" = 70) TABLESPACE "ts"', Indexes::options($postgres->index->definition->properties, Dialect::PostgreSql)->toString());
        self::assertSame('', Indexes::options(new Properties(), Dialect::PostgreSql)->toString());
    }
}
