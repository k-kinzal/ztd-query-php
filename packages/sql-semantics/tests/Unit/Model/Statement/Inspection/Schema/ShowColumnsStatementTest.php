<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowColumnsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowColumnsStatement::class)]
#[Medium]
final class ShowColumnsStatementTest extends TestCase
{
    public function testResultColumnsFollowTheServerLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)')))->bind('SHOW FIELDS IN users');
        self::assertInstanceOf(ShowColumnsStatement::class, $statement);
        self::assertSame('users', $statement->table->declaration->name);
        self::assertNull($statement->filter);
        self::assertFalse($statement->full);
        self::assertFalse($statement->extended);
        self::assertSame(['Field', 'Type', 'Null', 'Key', 'Default', 'Extra'], array_column($statement->resultColumns(), 'name'));
        self::assertSame('SHOW COLUMNS FROM `users`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testWithFullAddsCollationPrivilegesAndCommentImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)')))->bind("SHOW COLUMNS FROM users LIKE 'id%'");
        self::assertInstanceOf(ShowColumnsStatement::class, $statement);
        $changed = $statement->withFull(true);
        self::assertNotSame($statement, $changed);
        self::assertFalse($statement->full);
        self::assertSame(['Field', 'Type', 'Collation', 'Null', 'Key', 'Default', 'Extra', 'Privileges', 'Comment'], array_column($changed->resultColumns(), 'name'));
        self::assertSame("SHOW FULL COLUMNS FROM `users` LIKE 'id%'", (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithExtendedIncludesHiddenColumnsImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)')))->bind('SHOW FULL COLUMNS FROM users');
        self::assertInstanceOf(ShowColumnsStatement::class, $statement);
        $changed = $statement->withExtended(true);
        self::assertNotSame($statement, $changed);
        self::assertFalse($statement->extended);
        self::assertSame('SHOW EXTENDED FULL COLUMNS FROM `users`', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithTableDescribesAnotherTableImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT); CREATE TABLE orders(id INT)'));
        $statement = $binder->bind('SHOW COLUMNS FROM users');
        $other = $binder->bind('SHOW COLUMNS FROM orders');
        self::assertInstanceOf(ShowColumnsStatement::class, $statement);
        self::assertInstanceOf(ShowColumnsStatement::class, $other);
        $changed = $statement->withTable($other->table);
        self::assertNotSame($statement, $changed);
        self::assertSame('users', $statement->table->declaration->name);
        self::assertSame('orders', $changed->table->declaration->name);
        self::assertSame('SHOW COLUMNS FROM `orders`', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithFilterReplacesTheRestrictionImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)'));
        $statement = $binder->bind("SHOW COLUMNS FROM users LIKE 'id%'");
        $conditioned = $binder->bind("SHOW COLUMNS FROM users WHERE `Null` = 'NO'");
        self::assertInstanceOf(ShowColumnsStatement::class, $statement);
        self::assertInstanceOf(ShowColumnsStatement::class, $conditioned);
        self::assertInstanceOf(PatternFilter::class, $statement->filter);
        self::assertInstanceOf(ConditionFilter::class, $conditioned->filter);
        $changed = $statement->withFilter($conditioned->filter);
        self::assertNotSame($statement->filter, $changed->filter);
        self::assertSame("SHOW COLUMNS FROM `users` WHERE (`Null` = 'NO')", (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame('SHOW COLUMNS FROM `users`', (new \SqlSemantics\SimpleSerializer())->serialize($statement->withFilter(null)));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)')))->bind("SHOW EXTENDED FULL COLUMNS FROM users LIKE 'id%'");
        self::assertInstanceOf(ShowColumnsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame([$statement->table, $statement->filter, $statement->full, $statement->extended], [$copy->table, $copy->filter, $copy->full, $copy->extended]);
    }

    public function testRejectsAnAliasedTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)')))->bind('SHOW COLUMNS FROM users');
        self::assertInstanceOf(ShowColumnsStatement::class, $statement);
        $table = $statement->table;
        $this->expectException(InvalidStructure::class);
        new ShowColumnsStatement($statement->origin, new TableReference($table->id, $table->scopeId, $table->declaration, $table->name, 'u', $table->source));
    }

    public function testRejectsExtendedOnALegacyRelease(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE users(id INT)')))->bind('SHOW COLUMNS FROM users');
        self::assertInstanceOf(ShowColumnsStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ShowColumnsStatement($statement->origin, $statement->table, null, false, true);
    }
}
