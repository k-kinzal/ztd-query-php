<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Schema\TableDefinition;
use ZtdQuery\Schema\TableDefinitionRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(TableDefinition::class)]
#[CoversClass(TableDefinitionRegistry::class)]
final class TableDefinitionRegistryTest extends TestCase
{
    public function testRegisterAndGet(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id'], ['id' => 'INT'], [], [], []);

        $registry->register('users', $definition);

        self::assertSame($definition, $registry->get('users'));
    }

    public function testGetReturnsNullForUnknownTable(): void
    {
        $registry = new TableDefinitionRegistry();

        self::assertNull($registry->get('nonexistent'));
    }

    public function testHas(): void
    {
        $registry = new TableDefinitionRegistry();
        $definition = new TableDefinition(['id'], ['id' => 'INT'], [], [], []);

        $registry->register('users', $definition);

        self::assertTrue($registry->has('users'));
        self::assertFalse($registry->has('nonexistent'));
    }
}
