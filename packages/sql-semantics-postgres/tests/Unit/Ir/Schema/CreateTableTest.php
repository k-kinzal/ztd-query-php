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
use SqlSemantics\Platform\PostgreSql\Semantics;

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

    public function testToStringAdmitsAZeroColumnHeapAndItsSchemaComposition(): void
    {
        $semantics = new Semantics();
        $empty = $semantics->analyze('CREATE TABLE public.no_fields ()');
        self::assertInstanceOf(CreateTable::class, $empty);
        self::assertSame([], $empty->columns);
        self::assertSame('CREATE TABLE "public"."no_fields" () USING heap', $empty->toString());
        self::assertSame(
            'CREATE SCHEMA "batch" AUTHORIZATION CURRENT_USER CREATE TABLE "batch"."no_fields" () USING heap',
            $semantics->analyze('CREATE SCHEMA batch CREATE TABLE no_fields ()')->toString(),
        );
    }

    public function testToStringKeepsTheLastFieldAtThePhysicalWidthLimit(): void
    {
        $fields = array_map(
            static fn (int $slot): TableColumn => new TableColumn(new Identifier('c' . $slot), IntegerStorage::Standard),
            range(1, 1600),
        );
        $target = new TableName(new Identifier('public'), new Identifier('wide'));
        $table = new CreateTable($target, $fields);

        self::assertCount(1600, $table->columns);
        self::assertStringEndsWith('"c1600" INTEGER NULL) USING heap', $table->toString());
    }

    public function testToStringRejectsTheFirstWidthBeyondTheNativeLimit(): void
    {
        $fields = array_map(
            static fn (int $slot): TableColumn => new TableColumn(new Identifier('c' . $slot), IntegerStorage::Standard),
            range(1, 1601),
        );
        $target = new TableName(new Identifier('public'), new Identifier('wide'));
        $this->expectException(AnalysisException::class);
        $this->expectExceptionMessage('A PostgreSQL heap table can declare at most 1600 columns.');

        new CreateTable($target, $fields);
    }
}
