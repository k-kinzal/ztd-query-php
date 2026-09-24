<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Inspection\Definition\ShowCreateTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowCreateTableStatement::class)]
#[Medium]
final class ShowCreateTableStatementTest extends TestCase
{
    public function testResultColumnsFollowTheServerLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)')))->bind('SHOW CREATE TABLE users');
        self::assertInstanceOf(ShowCreateTableStatement::class, $statement);
        self::assertSame('users', $statement->table->declaration->name);
        self::assertSame(['Table', 'Create Table'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('SHOW CREATE TABLE `users`', $statement->toString());
    }

    public function testWithTableDescribesAnotherObjectImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT); CREATE TABLE orders(id INT)'));
        $statement = $binder->bind('SHOW CREATE TABLE users');
        $other = $binder->bind('SHOW CREATE TABLE orders');
        self::assertInstanceOf(ShowCreateTableStatement::class, $statement);
        self::assertInstanceOf(ShowCreateTableStatement::class, $other);
        $changed = $statement->withTable($other->table);
        self::assertNotSame($statement, $changed);
        self::assertSame('users', $statement->table->declaration->name);
        self::assertSame('orders', $changed->table->declaration->name);
        self::assertSame('SHOW CREATE TABLE `orders`', $changed->toString());
    }

    public function testWithOriginRetainsTheObject(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)')))->bind('SHOW CREATE TABLE users');
        self::assertInstanceOf(ShowCreateTableStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->table, $copy->table);
    }

    public function testRejectsAnAliasedObject(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)')))->bind('SHOW CREATE TABLE users');
        self::assertInstanceOf(ShowCreateTableStatement::class, $statement);
        $table = $statement->table;
        $this->expectException(InvalidStructure::class);
        new ShowCreateTableStatement($statement->origin, new TableReference($table->id, $table->scopeId, $table->declaration, $table->name, 'u', $table->source));
    }
}
