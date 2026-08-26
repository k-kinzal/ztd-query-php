<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeSchemaReflector;

#[CoversNothing]
final class SchemaReflectorTest extends TestCase
{
    public function testGetCreateStatementAnswersTheStatementATableWasCreatedWith(): void
    {
        $reflector = new FakeSchemaReflector(['users' => 'CREATE TABLE users (id INT)']);

        self::assertSame('CREATE TABLE users (id INT)', $reflector->getCreateStatement('users'));
    }

    public function testGetCreateStatementIsNothingForATableTheDatabaseDoesNotHave(): void
    {
        self::assertNull((new FakeSchemaReflector())->getCreateStatement('users'));
    }

    public function testReflectAllAnswersEveryTableKeyedByItsName(): void
    {
        $reflector = new FakeSchemaReflector(['users' => 'CREATE TABLE users (id INT)']);
        $reflector->addTable('orders', 'CREATE TABLE orders (id INT)');

        self::assertSame(
            ['users' => 'CREATE TABLE users (id INT)', 'orders' => 'CREATE TABLE orders (id INT)'],
            $reflector->reflectAll(),
        );
    }

    public function testReflectAllIsEmptyForADatabaseWithNoTables(): void
    {
        self::assertSame([], (new FakeSchemaReflector())->reflectAll());
    }
}
