<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\AnalysisException;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\PrimaryKey;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;

#[CoversClass(CreateTable::class)]
#[Small]
final class CreateTableTest extends TestCase
{
    public function testProfileIdIdentifiesTheNativeDeclarationProfile(): void
    {
        $name = new Identifier('a');
        $table = new CreateTable(new TableName(new Identifier('app'), $name), [new TableColumn($name, IntegerStorage::Standard)]);

        self::assertStringStartsWith('pg-17.2/', $table->profileId());
    }

    public function testToStringKeepsDefinitionOrderAndStorage(): void
    {
        $table = new CreateTable(new TableName(new Identifier('app'), new Identifier('t')), [
            new TableColumn(new Identifier('b'), IntegerStorage::Wide),
            new TableColumn(new Identifier('a'), IntegerStorage::Small, false),
        ], true);

        self::assertSame('CREATE TABLE IF NOT EXISTS "app"."t" ("b" BIGINT NULL, "a" SMALLINT NOT NULL) USING heap', $table->toString());
    }

    public function testToStringRejectsAnUnboundIncludedReference(): void
    {
        $name = new Identifier('a');
        $table = new TableName(new Identifier('app'), new Identifier('t'));
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A primary key reference must select a declared column.');

        new CreateTable($table, [new TableColumn($name, IntegerStorage::Standard)], primaryKey: new PrimaryKey([$name], [new Identifier('missing')]));
    }
}
