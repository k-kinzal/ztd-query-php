<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;

#[UsesClass(TableDefinition::class)]
#[UsesClass(TableDefinitionRegistry::class)]
#[CoversClass(SchemaRegistry::class)]
final class SchemaRegistryTest extends TestCase
{
    public function testGetAllClearRegisterAndClear(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id'], ['id' => 'INT'], [], [], []);
        $registry->register('users', $definition);

        self::assertSame($definition, $registry->get('users'));
        self::assertSame(['users' => $definition], $registry->getAll());
        self::assertTrue($registry->has('users'));
        self::assertTrue($registry->hasAnyTables());

        $registry->clear();

        self::assertNull($registry->get('users'));
        self::assertSame([], $registry->getAll());
        self::assertFalse($registry->hasAnyTables());
    }

    public function testRegisterGetUnregister(): void
    {
        $registry = new TableDefinitionRegistry();
        $usersDef = new TableDefinition(['id'], ['id' => 'INT'], [], [], []);
        $postsDef = new TableDefinition(['id'], ['id' => 'INT'], [], [], []);
        $registry->register('users', $usersDef);
        $registry->register('posts', $postsDef);

        $registry->unregister('users');

        self::assertNull($registry->get('users'));
        self::assertSame($postsDef, $registry->get('posts'));
    }

    public function testHasAnswersForATableTheRegistryKnows(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('order', 'CREATE TABLE `order` (id INT)');

        self::assertTrue($registry->has('order'));
        self::assertFalse($registry->has('other'));
    }

    public function testHasAnyTablesIsFalseUntilOneIsRegistered(): void
    {
        $registry = new SchemaRegistry();

        self::assertFalse($registry->hasAnyTables());

        $registry->register('order', 'CREATE TABLE `order` (id INT)');

        self::assertTrue($registry->hasAnyTables());
    }

    public function testUnregisterLeavesTheRegistryWithoutThatTable(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('order', 'CREATE TABLE `order` (id INT)');

        $registry->unregister('order');

        self::assertNull($registry->get('order'));
    }

    public function testClearLeavesTheRegistryWithNoTablesAtAll(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('order', 'CREATE TABLE `order` (id INT)');

        $registry->clear();

        self::assertFalse($registry->hasAnyTables());
        self::assertSame([], $registry->getAll());
    }
}
