<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\CreateTable;
use SqlSemantics\Platform\Sqlite\Ir\Schema\DeclaredDatatypeName;
use SqlSemantics\Platform\Sqlite\Ir\Schema\NotNullConflict;
use SqlSemantics\Platform\Sqlite\Ir\Schema\TableColumn;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Schema\CreateTable
 */
final class CreateTableTest extends TestCase
{
    public function testProfileIdIdentifiesSqlite(): void
    {
        $columns = [new TableColumn(new Identifier('id'))];
        $table = new CreateTable(new Identifier('main'), new Identifier('records'), $columns);

        self::assertSame('sqlite-3.47.2/utf8/short-column-names/numbered-parameters', $table->profileId());
    }

    public function testToStringKeepsColumnOrderWhenTheCallerModifiesItsArray(): void
    {
        $column = new TableColumn(new Identifier('value'), DeclaredDatatypeName::Text, NotNullConflict::Abort);
        $columns = [&$column, new TableColumn(new Identifier('id'), DeclaredDatatypeName::Integer)];
        $table = new CreateTable(new Identifier('temp'), new Identifier('records'), $columns, true);
        $column = new TableColumn(new Identifier('replaced'));

        self::assertSame('CREATE TABLE IF NOT EXISTS "temp"."records" ("value" TEXT NOT NULL, "id" INTEGER)', $table->toString());
    }

    public function testToStringAddsStrictAfterAllTheColumns(): void
    {
        $columns = [
            new TableColumn(new Identifier('value'), DeclaredDatatypeName::Any),
            new TableColumn(new Identifier('id'), DeclaredDatatypeName::Int),
        ];
        $table = new CreateTable(new Identifier('main'), new Identifier('records'), $columns, strict: true);

        self::assertSame('CREATE TABLE "main"."records" ("value" ANY, "id" INT) STRICT', $table->toString());
    }
}
