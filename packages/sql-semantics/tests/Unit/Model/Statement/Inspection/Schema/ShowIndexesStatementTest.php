<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Inspection\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Inspection\Schema\ShowIndexesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ShowIndexesStatement::class)]
#[Medium]
final class ShowIndexesStatementTest extends TestCase
{
    public function testResultColumnsFollowTheServerLayout(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)')))->bind('SHOW KEYS IN users');
        self::assertInstanceOf(ShowIndexesStatement::class, $statement);
        self::assertSame('users', $statement->table->declaration->name);
        self::assertNull($statement->condition);
        self::assertFalse($statement->extended);
        self::assertCount(15, $statement->resultColumns());
        self::assertSame('Visible', $statement->resultColumns()[13]->name);
        self::assertSame('SHOW INDEX FROM `users`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testResultColumnsOmitLaterFieldsOnLegacyReleases(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build('CREATE TABLE users(id INT)')))->bind('SHOW INDEXES FROM users');
        self::assertInstanceOf(ShowIndexesStatement::class, $statement);
        self::assertCount(13, $statement->resultColumns());
        self::assertSame('Index_comment', $statement->resultColumns()[12]->name);
    }

    public function testWithConditionReplacesTheRestrictionImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)'));
        $statement = $binder->bind('SHOW INDEX FROM users');
        $conditioned = $binder->bind('SHOW INDEX FROM users WHERE Non_unique = 0');
        self::assertInstanceOf(ShowIndexesStatement::class, $statement);
        self::assertInstanceOf(ShowIndexesStatement::class, $conditioned);
        self::assertInstanceOf(ConditionFilter::class, $conditioned->condition);
        $changed = $statement->withCondition($conditioned->condition);
        self::assertNotSame($statement, $changed);
        self::assertNull($statement->condition);
        self::assertSame('SHOW INDEX FROM `users` WHERE (`Non_unique` = 0)', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
        self::assertSame('SHOW INDEX FROM `users`', (new \SqlSemantics\SimpleSerializer())->serialize($changed->withCondition(null)));
    }

    public function testWithExtendedIncludesHiddenKeyPartsImmutably(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)')))->bind('SHOW INDEX FROM users');
        self::assertInstanceOf(ShowIndexesStatement::class, $statement);
        $changed = $statement->withExtended(true);
        self::assertNotSame($statement, $changed);
        self::assertFalse($statement->extended);
        self::assertTrue($changed->extended);
        self::assertSame('SHOW EXTENDED INDEX FROM `users`', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithTableListsAnotherTableImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT); CREATE TABLE orders(id INT)'));
        $statement = $binder->bind('SHOW INDEX FROM users');
        $other = $binder->bind('SHOW INDEX FROM orders');
        self::assertInstanceOf(ShowIndexesStatement::class, $statement);
        self::assertInstanceOf(ShowIndexesStatement::class, $other);
        $changed = $statement->withTable($other->table);
        self::assertSame('users', $statement->table->declaration->name);
        self::assertSame('orders', $changed->table->declaration->name);
        self::assertSame('SHOW INDEX FROM `orders`', (new \SqlSemantics\SimpleSerializer())->serialize($changed));
    }

    public function testWithOriginRetainsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)')))->bind('SHOW EXTENDED INDEX FROM users WHERE Non_unique = 0');
        self::assertInstanceOf(ShowIndexesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame([$statement->table, $statement->condition, $statement->extended], [$copy->table, $copy->condition, $copy->extended]);
    }

    public function testRejectsAnAliasedTable(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE users(id INT)')))->bind('SHOW INDEX FROM users');
        self::assertInstanceOf(ShowIndexesStatement::class, $statement);
        $table = $statement->table;
        $this->expectException(InvalidStructure::class);
        new ShowIndexesStatement($statement->origin, new TableReference($table->id, $table->scopeId, $table->declaration, $table->name, 'u', $table->source));
    }

    public function testRejectsExtendedOnALegacyRelease(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build('CREATE TABLE users(id INT)')))->bind('SHOW INDEX FROM users');
        self::assertInstanceOf(ShowIndexesStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new ShowIndexesStatement($statement->origin, $statement->table, null, true);
    }
}
