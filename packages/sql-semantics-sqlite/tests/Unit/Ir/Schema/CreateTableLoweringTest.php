<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Ir\Schema\CreateTableLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\Storage;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Key\KeyConflict;
use SqlSemantics\Platform\Sqlite\Ir\Schema\NotNullConflict;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable as ParsedCreateTable;
use SqlSemantics\Testing\Sqlite\Inspection;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Schema\CreateTableLowering
 */
final class CreateTableLoweringTest extends TestCase
{
    public function testLowerResolvesTempAndCanonicalizesNativeDeclaredDatatypeNames(): void
    {
        $semantics = new Semantics();
        $sql = 'CREATE TEMP TABLE data (s "tExT" NOT NULL, n integer, raw)';
        $operation = (new Inspection())->inspect($sql);
        self::assertInstanceOf(ParsedCreateTable::class, $operation->statement);

        $value = (new CreateTableLowering())->lower($operation->binding(), $operation->statement);

        self::assertSame('temp', $value->schema->value);
        self::assertSame(DeclaredDatatypeName::Text, $value->columns[0]->type);
        self::assertSame(NotNullConflict::Abort, $value->columns[0]->notNull);
        self::assertSame(DeclaredDatatypeName::Integer, $value->columns[1]->type);
        self::assertSame(DeclaredDatatypeName::Untyped, $value->columns[2]->type);
        $published = $semantics->analyze($sql);
        self::assertInstanceOf(CreateTable::class, $published);
        self::assertEquals($value, $published);
    }

    public function testAnalyzeKeepsStrictStorageAndEachNativeTypeName(): void
    {
        $value = (new Semantics())->analyze('CREATE TABLE data (i INT, j INTEGER, v ANY) STRICT, STRICT');

        self::assertInstanceOf(CreateTable::class, $value);
        self::assertTrue($value->strict);
        self::assertSame([DeclaredDatatypeName::Int, DeclaredDatatypeName::Integer, DeclaredDatatypeName::Any], array_column($value->columns, 'type'));
        self::assertSame('CREATE TABLE "main"."data" ("i" INT, "j" INTEGER, "v" ANY) STRICT', $value->toString());
    }

    public function testAnalyzeKeepsTheResolvedPrimaryKeySlotsInDeclaredOrder(): void
    {
        $value = (new Semantics())->analyze(
            'CREATE TEMP TABLE data (code TEXT, id INTEGER, PRIMARY KEY (ID DESC, code COLLATE NOCASE) ON CONFLICT FAIL) WITHOUT ROWID',
        );

        self::assertInstanceOf(CreateTable::class, $value);
        self::assertNotNull($value->primaryKey);
        self::assertTrue($value->withoutRowid);
        self::assertSame([1, 0], array_column($value->primaryKey->terms, 'slot'));
        self::assertSame(KeyConflict::Fail, $value->primaryKey->conflict);
        self::assertSame('CREATE TABLE "temp"."data" ("code" TEXT, "id" INTEGER, PRIMARY KEY ("id" DESC, "code" COLLATE NOCASE) ON CONFLICT FAIL) WITHOUT ROWID', $value->toString());
    }

    public function testAnalyzePublishesPersistentRowidAllocation(): void
    {
        $value = (new Semantics())->analyze('CREATE TABLE data (id INTEGER PRIMARY KEY AUTOINCREMENT)');

        self::assertInstanceOf(CreateTable::class, $value);
        self::assertNotNull($value->primaryKey);
        self::assertTrue($value->primaryKey->autoIncrement);
        self::assertSame('CREATE TABLE "main"."data" ("id" INTEGER PRIMARY KEY AUTOINCREMENT)', $value->toString());
    }

    public function testAnalyzeBindsForwardGeneratedSlotsAndResolvesStorage(): void
    {
        $semantics = new Semantics();
        $value = $semantics->analyze('CREATE TABLE data (first INTEGER GENERATED ALWAYS AS (later) STORED, a INTEGER, later AS (a))');

        self::assertInstanceOf(CreateTable::class, $value);
        $first = $value->columns[0]->generated;
        $later = $value->columns[2]->generated;
        self::assertNotNull($first);
        self::assertNotNull($later);
        self::assertEquals(new ColumnReference(2), $first->expression);
        self::assertEquals(new ColumnReference(1), $later->expression);
        self::assertSame(Storage::Stored, $first->mode);
        self::assertSame(Storage::Virtual, $later->mode);
        self::assertSame(DeclaredDatatypeName::Untyped, $value->columns[2]->type);
        self::assertEquals($value, $semantics->analyze($value->toString()));
    }
}
