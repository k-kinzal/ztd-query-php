<?php

declare(strict_types=1);

namespace Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Exception\ForeignKeyViolationException;

#[CoversClass(ForeignKeyViolationException::class)]
final class ForeignKeyViolationExceptionTest extends TestCase
{
    public function testGetMessageReturnsFormattedMessage(): void
    {
        $exception = new ForeignKeyViolationException(
            'INSERT INTO orders (user_id) VALUES (999)',
            'orders',
            'fk_orders_users',
            'users',
            'id'
        );

        self::assertSame(
            "Foreign key constraint 'fk_orders_users' violated: referenced row not found in 'users.id'.",
            $exception->getMessage()
        );
    }

    public function testGetSqlReturnsOriginalSql(): void
    {
        $sql = 'INSERT INTO orders (user_id) VALUES (999)';
        $exception = new ForeignKeyViolationException($sql, 'orders', 'fk_orders_users', 'users', 'id');

        self::assertSame($sql, $exception->getSql());
    }

    public function testGetTableNameReturnsTableName(): void
    {
        $exception = new ForeignKeyViolationException('sql', 'orders', 'fk_orders_users', 'users', 'id');

        self::assertSame('orders', $exception->getTableName());
    }

    public function testGetConstraintNameReturnsConstraintName(): void
    {
        $exception = new ForeignKeyViolationException('sql', 'orders', 'fk_orders_users', 'users', 'id');

        self::assertSame('fk_orders_users', $exception->getConstraintName());
    }

    public function testGetReferencedTableReturnsReferencedTable(): void
    {
        $exception = new ForeignKeyViolationException('sql', 'orders', 'fk_orders_users', 'users', 'id');

        self::assertSame('users', $exception->getReferencedTable());
    }

    public function testGetReferencedColumnReturnsReferencedColumn(): void
    {
        $exception = new ForeignKeyViolationException('sql', 'orders', 'fk_orders_users', 'users', 'id');

        self::assertSame('id', $exception->getReferencedColumn());
    }

    public function testOfNamesTheFirstColumnTheKeyPointsAt(): void
    {
        $exception = ForeignKeyViolationException::of('DELETE', 'children', 'fk', 'parents', ['id', 'other']);

        self::assertSame('id', $exception->getReferencedColumn());
        self::assertSame('children', $exception->getTableName());
    }

    public function testOfNamesNoColumnWhereTheKeyPointsAtNone(): void
    {
        $exception = ForeignKeyViolationException::of('DELETE', 'children', 'fk', 'parents', []);

        self::assertSame('', $exception->getReferencedColumn());
    }
}
