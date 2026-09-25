<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Statement\Definition\PostgreSql\DropForeignDataWrappersStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\DropForeignServersStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\ForeignRemovals::class)]
#[Medium]
final class ForeignRemovalsTest extends TestCase
{
    public function testBindServerRemovalDoesNotInterpretQuotedNamesAsClauses(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP SERVER "IF", "CASCADE" RESTRICT');
        self::assertInstanceOf(DropForeignServersStatement::class, $statement);
        self::assertSame(['IF', 'CASCADE'], $statement->names);
        self::assertFalse($statement->ifExists);
        self::assertSame(DropBehavior::Restrict, $statement->behavior);
    }

    public function testBindWrapperRemovalHasAnIndependentOperationAndPolicies(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP FOREIGN DATA WRAPPER IF EXISTS one, two CASCADE');
        self::assertInstanceOf(DropForeignDataWrappersStatement::class, $statement);
        self::assertSame(['one', 'two'], $statement->names);
        self::assertTrue($statement->ifExists);
        self::assertSame(DropBehavior::Cascade, $statement->behavior);
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['drop server s restrict', 'DROP SERVER "s" RESTRICT'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['drop foreign data wrapper w cascade', 'DROP FOREIGN DATA WRAPPER "w" CASCADE'])]
    public function testBindReadsLowerCaseRemovals(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)));
    }
}
