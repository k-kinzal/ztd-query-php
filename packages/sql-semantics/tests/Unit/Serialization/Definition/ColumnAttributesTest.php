<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\CreateTableStatement;
use SqlSemantics\Schema\Column\Attributes;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\ColumnAttributes;

#[CoversClass(ColumnAttributes::class)]
#[Medium]
final class ColumnAttributesTest extends TestCase
{
    public function testWriteSerializesEveryDeclaredAttributeWithItsBoundary(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("CREATE TABLE t (a VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT 'c' ENGINE_ATTRIBUTE '{}' SECONDARY_ENGINE_ATTRIBUTE '{}' STORAGE DISK COLUMN_FORMAT FIXED INVISIBLE, b INT ZEROFILL, c POINT SRID 4326, e CHAR(3) BINARY, f INT VISIBLE)");
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        $expected = "CREATE TABLE `t`(`a` varchar(10) CHARACTER SET `utf8mb4` COLLATE `utf8mb4_bin` COMMENT 'c' ENGINE_ATTRIBUTE '{}' SECONDARY_ENGINE_ATTRIBUTE '{}' STORAGE DISK COLUMN_FORMAT FIXED INVISIBLE, `b` integer UNSIGNED ZEROFILL, `c` point SRID 4326, `e` char(3) BINARY, `f` integer VISIBLE)";
        self::assertSame($expected, $statement->toString());
        $rebound = $binder->bind($expected);
        self::assertInstanceOf(CreateTableStatement::class, $rebound);
        self::assertSame('c', $rebound->definition->table->columns[0]->attributes->comment);
        self::assertSame(4326, $rebound->definition->table->columns[2]->attributes->spatialReferenceId);
        self::assertTrue($rebound->definition->table->columns[1]->attributes->zeroFill);
        self::assertSame($expected, $rebound->toString());
    }

    public function testWriteOrdersStorageBeforeFormatRegardlessOfSourceOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE TABLE t (c TEXT COLUMN_FORMAT DYNAMIC STORAGE MEMORY, d INT INVISIBLE)');
        self::assertInstanceOf(CreateTableStatement::class, $statement);
        self::assertSame('STORAGE MEMORY COLUMN_FORMAT DYNAMIC', ColumnAttributes::write($statement->definition->table->columns[0]->attributes, Dialect::MySql)->toString());
        self::assertSame('INVISIBLE', ColumnAttributes::write($statement->definition->table->columns[1]->attributes, Dialect::MySql)->toString());
    }

    public function testWriteProducesNothingWithoutDeclaredAttributes(): void
    {
        self::assertSame('', ColumnAttributes::write(new Attributes(), Dialect::MySql)->toString());
    }

    public function testWriteEncodesCommentTextAsALiteral(): void
    {
        self::assertSame("COMMENT 'it''s'", ColumnAttributes::write(new Attributes(comment: "it's"), Dialect::PostgreSql)->toString());
    }


    public function testWritePlacesPostgreSqlStorageAndCompressionBeforeConstraints(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a int)'));
        $statement = $binder->bind('ALTER TABLE t ADD COLUMN c text STORAGE EXTERNAL COMPRESSION pglz COLLATE "C" NOT NULL');
        self::assertSame('ALTER TABLE "t" ADD COLUMN "c" text STORAGE EXTERNAL COMPRESSION "pglz" COLLATE "C" NOT NULL', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
