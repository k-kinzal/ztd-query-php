<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use ZtdQuery\Schema\SchemaRegistry;

#[UsesClass(TableDefinition::class)]
#[UsesClass(TableDefinitionRegistry::class)]
#[CoversClass(SchemaRegistry::class)]
final class SchemaRegistryTest extends TestCase
{
    public function testRegisterAndClear(): void
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

    public function testUnregister(): void
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
}
