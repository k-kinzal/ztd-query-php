<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Inspection\Definition\ShowCreateViewStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowCreateViewStatement::class)]
#[Medium]
final class ShowCreateViewStatementTest extends TestCase
{
    public function testResultColumnsFollowTheServerLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)')))->bind('SHOW CREATE VIEW users');
        self::assertInstanceOf(ShowCreateViewStatement::class, $statement);
        self::assertSame('users', $statement->view->declaration->name);
        self::assertSame(['View', 'Create View', 'character_set_client', 'collation_connection'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('SHOW CREATE VIEW `users`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithViewDescribesAnotherObjectImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT); CREATE TABLE orders(id INT)'));
        $statement = $binder->bind('SHOW CREATE VIEW users');
        $other = $binder->bind('SHOW CREATE VIEW orders');
        self::assertInstanceOf(ShowCreateViewStatement::class, $statement);
        self::assertInstanceOf(ShowCreateViewStatement::class, $other);
        $changed = $statement->withView($other->view);
        self::assertNotSame($statement, $changed);
        self::assertSame('users', $statement->view->declaration->name);
        self::assertSame('orders', $changed->view->declaration->name);
        self::assertSame('SHOW CREATE VIEW `orders`', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithOriginRetainsTheObject(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)')))->bind('SHOW CREATE VIEW users');
        self::assertInstanceOf(ShowCreateViewStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->view, $copy->view);
    }

    public function testRejectsAnAliasedObject(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)')))->bind('SHOW CREATE VIEW users');
        self::assertInstanceOf(ShowCreateViewStatement::class, $statement);
        $table = $statement->view;
        $this->expectException(InvalidStructure::class);
        new ShowCreateViewStatement($statement->origin, new TableReference($table->id, $table->scopeId, $table->declaration, $table->name, 'u', $table->source));
    }
}
