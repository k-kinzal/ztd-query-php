<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName;
use SqlSemantics\Platform\Sqlite\Ir\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Ir\Schema\CreateTableLowering;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateTable as ParsedCreateTable;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Schema\CreateTableLowering
 */
final class CreateTableLoweringTest extends TestCase
{
    public function testLowerResolvesTempAndCanonicalizesNativeDeclaredDatatypeNames(): void
    {
        $semantics = new Semantics();
        $sql = 'CREATE TEMP TABLE data (s "tExT" NOT NULL, n integer, raw)';
        $operation = $semantics->inspect($sql);
        self::assertInstanceOf(ParsedCreateTable::class, $operation->statement);

        $value = (new CreateTableLowering())->lower($operation, $operation->statement);

        self::assertSame('temp', $value->schema->value);
        self::assertSame(DeclaredDatatypeName::Text, $value->columns[0]->type);
        self::assertTrue($value->columns[0]->notNull);
        self::assertSame(DeclaredDatatypeName::Integer, $value->columns[1]->type);
        self::assertSame(DeclaredDatatypeName::Untyped, $value->columns[2]->type);
        $published = $semantics->analyze($sql);
        self::assertInstanceOf(CreateTable::class, $published);
        self::assertEquals($value, $published);
    }
}
