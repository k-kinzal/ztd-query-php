<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use ZtdQuery\Schema\SchemaReflector;
use ZtdQuery\Schema\SchemaRegistry;
use PHPUnit\Framework\TestCase;

final class SchemaRegistryTest extends TestCase
{
    public function testRegisterAndClear(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT)');

        $this->assertSame('CREATE TABLE users (id INT)', $registry->get('users'));
        $this->assertSame(['users' => 'CREATE TABLE users (id INT)'], $registry->getAll());

        $registry->clear();
        $this->assertNull($registry->get('users'));
        $this->assertSame([], $registry->getAll());
    }

    public function testColumnsAndPrimaryKeysUseReflector(): void
    {
        $reflector = new CountingReflector();
        $registry = new SchemaRegistry($reflector);

        $columns = $registry->getColumns('users');
        $this->assertSame(['id', 'name'], $columns);
        $this->assertSame(['id'], $registry->getPrimaryKeys('users'));
        $this->assertSame(1, $reflector->primaryKeyCalls);

        $registry->getPrimaryKeys('users');
        $this->assertSame(1, $reflector->primaryKeyCalls);

        $registry->getColumns('users');
        $this->assertSame(1, $reflector->createCalls);
    }

    public function testGetColumnsReturnsNullWhenSchemaMissing(): void
    {
        $registry = new SchemaRegistry();

        $this->assertNull($registry->getColumns('missing'));
    }

    public function testGetColumnsReturnsNullWhenReflectorHasNoSchema(): void
    {
        $reflector = new CountingReflector();
        $registry = new SchemaRegistry($reflector);

        $this->assertNull($registry->getColumns('missing'));
        $this->assertSame(1, $reflector->createCalls);
    }

    public function testGetPrimaryKeysReturnsEmptyWithoutReflector(): void
    {
        $registry = new SchemaRegistry();

        $this->assertSame([], $registry->getPrimaryKeys('missing'));
    }

    public function testGetNotNullColumnsReturnsNotNullColumns(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255))');

        $notNullColumns = $registry->getNotNullColumns('users');

        // id is PRIMARY KEY (implicitly NOT NULL), name is explicitly NOT NULL
        $this->assertContains('id', $notNullColumns);
        $this->assertContains('name', $notNullColumns);
        $this->assertNotContains('email', $notNullColumns);
    }

    public function testGetNotNullColumnsReturnsEmptyForMissingTable(): void
    {
        $registry = new SchemaRegistry();

        $this->assertSame([], $registry->getNotNullColumns('missing'));
    }

    public function testGetUniqueConstraintsReturnsUniqueConstraints(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, email VARCHAR(255) UNIQUE, name VARCHAR(255))');

        $constraints = $registry->getUniqueConstraints('users');

        // email has inline UNIQUE constraint
        $this->assertArrayHasKey('email_UNIQUE', $constraints);
        $this->assertSame(['email'], $constraints['email_UNIQUE']);
    }

    public function testGetUniqueConstraintsReturnsEmptyForMissingTable(): void
    {
        $registry = new SchemaRegistry();

        $this->assertSame([], $registry->getUniqueConstraints('missing'));
    }

    public function testHasColumnReturnsTrueForExistingColumn(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');

        $this->assertTrue($registry->hasColumn('users', 'id'));
        $this->assertTrue($registry->hasColumn('users', 'name'));
    }

    public function testHasColumnReturnsFalseForNonExistingColumn(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255))');

        $this->assertFalse($registry->hasColumn('users', 'email'));
        $this->assertFalse($registry->hasColumn('users', 'missing'));
    }

    public function testHasColumnReturnsFalseForMissingTable(): void
    {
        $registry = new SchemaRegistry();

        $this->assertFalse($registry->hasColumn('missing', 'id'));
    }

    public function testUnregisterClearsAllCaches(): void
    {
        $registry = new SchemaRegistry();
        $registry->register('users', 'CREATE TABLE users (id INT PRIMARY KEY, name VARCHAR(255) NOT NULL, email VARCHAR(255) UNIQUE)');

        // Populate caches
        $registry->getColumns('users');
        $registry->getPrimaryKeys('users');
        $registry->getNotNullColumns('users');
        $registry->getUniqueConstraints('users');

        $registry->unregister('users');

        $this->assertNull($registry->get('users'));
        $this->assertNull($registry->getColumns('users'));
        $this->assertSame([], $registry->getPrimaryKeys('users'));
        $this->assertSame([], $registry->getNotNullColumns('users'));
        $this->assertSame([], $registry->getUniqueConstraints('users'));
    }
}

final class CountingReflector implements SchemaReflector
{
    /**
     * Count of getPrimaryKeys calls for caching assertions.
     *
     * @var int
     */
    public int $primaryKeyCalls = 0;

    /**
     * Count of getCreateStatement calls for caching assertions.
     *
     * @var int
     */
    public int $createCalls = 0;

    public function getCreateStatement(string $tableName): ?string
    {
        $this->createCalls++;
        if ($tableName !== 'users') {
            return null;
        }

        return 'CREATE TABLE users (id INT, name VARCHAR(255))';
    }

    public function getPrimaryKeys(string $tableName): array
    {
        $this->primaryKeyCalls++;
        if ($tableName !== 'users') {
            return [];
        }

        return ['id'];
    }
}
