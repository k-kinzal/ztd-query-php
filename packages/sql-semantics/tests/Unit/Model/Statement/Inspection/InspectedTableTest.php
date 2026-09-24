<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Inspection\Definition\ShowCreateTableStatement;
use SqlSemantics\Model\Statement\Inspection\InspectedTable;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowTablesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(InspectedTable::class)]
#[Medium]
final class InspectedTableTest extends TestCase
{
    public function testValidateRejectsAnAliasedTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)')))->bind('SHOW CREATE TABLE users');
        self::assertInstanceOf(ShowCreateTableStatement::class, $statement);
        $table = $statement->table;
        InspectedTable::validate($statement->origin, $table);
        $this->expectException(InvalidStructure::class);
        InspectedTable::validate($statement->origin, new TableReference($table->id, $table->scopeId, $table->declaration, $table->name, 'u', $table->source));
    }

    public function testValidateRejectsATableFromAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)')))->bind('SHOW CREATE TABLE users');
        self::assertInstanceOf(ShowCreateTableStatement::class, $statement);
        $foreign = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE users(id INT)')))->bind('TABLE users');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\TableStatement::class, $foreign);
        self::assertInstanceOf(TableReference::class, $foreign->from);
        $this->expectException(InvalidStructure::class);
        InspectedTable::validate($statement->origin, $foreign->from);
    }

    public function testExtendedRequiresARelease8Grammar(): void
    {
        $legacy = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SHOW TABLES');
        self::assertInstanceOf(ShowTablesStatement::class, $legacy);
        InspectedTable::extended($legacy->origin, false);
        $this->expectException(InvalidStructure::class);
        InspectedTable::extended($legacy->origin, true);
    }
}
