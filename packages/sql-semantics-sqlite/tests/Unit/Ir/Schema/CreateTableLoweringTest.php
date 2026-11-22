<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Ir\Schema\CreateTableLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName;
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
}
