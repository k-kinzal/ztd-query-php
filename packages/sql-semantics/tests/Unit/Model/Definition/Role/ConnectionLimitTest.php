<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Role\ConnectionLimit;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\CreateRoleStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ConnectionLimit::class)]
#[Medium]
final class ConnectionLimitTest extends TestCase
{
    #[TestWith([-1])]
    #[TestWith([0])]
    #[TestWith([10])]
    #[TestWith([2147483647])]
    public function testRetainsAnyLimitFromUnlimitedToTheSignedRangeTop(int $limit): void
    {
        self::assertSame($limit, (new ConnectionLimit($limit))->limit);
    }

    #[TestWith([-2])]
    #[TestWith([2147483648])]
    #[TestWith([PHP_INT_MIN])]
    public function testRejectsALimitOutsideTheSignedRange(int $limit): void
    {
        $this->expectException(InvalidStructure::class);
        new ConnectionLimit($limit);
    }

    public function testConnectionLimitBindsFromADefinition(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE ROLE r CONNECTION LIMIT 10');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        $option = $statement->options[0];
        self::assertInstanceOf(ConnectionLimit::class, $option);
        self::assertSame(10, $option->limit);
        self::assertSame('CREATE ROLE "r" CONNECTION LIMIT 10', $statement->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(CreateRoleStatement::class, $rebound);
        self::assertSame($statement->toString(), $rebound->toString());
    }
}
