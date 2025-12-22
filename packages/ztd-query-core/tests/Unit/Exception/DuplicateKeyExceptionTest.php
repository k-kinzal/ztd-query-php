<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZtdQuery\Exception\DuplicateKeyException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DuplicateKeyException::class)]
final class DuplicateKeyExceptionTest extends TestCase
{
    public function testGetMessageReturnsFormattedMessage(): void
    {
        $exception = new DuplicateKeyException(
            'INSERT INTO users (id, name) VALUES (1, "Alice")',
            'users',
            'PRIMARY',
            ['id' => 1]
        );

        self::assertSame("Duplicate entry '1' for key 'PRIMARY' in table 'users'.", $exception->getMessage());
    }

    public function testGetMessageWithMultipleKeyValues(): void
    {
        $exception = new DuplicateKeyException(
            'INSERT INTO orders (user_id, product_id) VALUES (1, 2)',
            'orders',
            'user_product_unique',
            ['user_id' => 1, 'product_id' => 2]
        );

        self::assertSame(
            "Duplicate entry '1, 2' for key 'user_product_unique' in table 'orders'.",
            $exception->getMessage()
        );
    }

    public function testGetMessageWithStringKeyValue(): void
    {
        $exception = new DuplicateKeyException(
            'INSERT INTO users (email) VALUES ("test@example.com")',
            'users',
            'email_UNIQUE',
            ['email' => 'test@example.com']
        );

        self::assertSame(
            "Duplicate entry ''test@example.com'' for key 'email_UNIQUE' in table 'users'.",
            $exception->getMessage()
        );
    }

    public function testGetMessageWithNullKeyValue(): void
    {
        $exception = new DuplicateKeyException(
            'INSERT INTO users (id) VALUES (NULL)',
            'users',
            'PRIMARY',
            ['id' => null]
        );

        self::assertSame("Duplicate entry '' for key 'PRIMARY' in table 'users'.", $exception->getMessage());
    }

    public function testGetMessageWithEmptyKeyValues(): void
    {
        $exception = new DuplicateKeyException(
            'INSERT INTO users (id) VALUES (1)',
            'users',
            'PRIMARY'
        );

        self::assertSame("Duplicate entry '' for key 'PRIMARY' in table 'users'.", $exception->getMessage());
    }

    public function testGetSqlReturnsOriginalSql(): void
    {
        $sql = 'INSERT INTO users (id) VALUES (1)';
        $exception = new DuplicateKeyException($sql, 'users', 'PRIMARY', ['id' => 1]);

        self::assertSame($sql, $exception->getSql());
    }

    public function testGetTableNameReturnsTableName(): void
    {
        $exception = new DuplicateKeyException('sql', 'users', 'PRIMARY', ['id' => 1]);

        self::assertSame('users', $exception->getTableName());
    }

    public function testGetKeyNameReturnsKeyName(): void
    {
        $exception = new DuplicateKeyException('sql', 'users', 'PRIMARY', ['id' => 1]);

        self::assertSame('PRIMARY', $exception->getKeyName());
    }

    public function testGetKeyValuesReturnsKeyValues(): void
    {
        $keyValues = ['id' => 1, 'name' => 'Alice'];
        $exception = new DuplicateKeyException('sql', 'users', 'PRIMARY', $keyValues);

        self::assertSame($keyValues, $exception->getKeyValues());
    }

    public function testExtendsRuntimeException(): void
    {
        $exception = new DuplicateKeyException('sql', 'table', 'key');

        self::assertInstanceOf(RuntimeException::class, $exception);
    }
}
