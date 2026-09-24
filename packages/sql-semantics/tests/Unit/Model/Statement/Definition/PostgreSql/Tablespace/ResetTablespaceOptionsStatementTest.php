<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Tablespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Tablespace\ResetTablespaceOptionsStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ResetTablespaceOptionsStatement::class)]
#[Medium]
final class ResetTablespaceOptionsStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRemovals(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLESPACE fast RESET (seq_page_cost)');
        self::assertInstanceOf(ResetTablespaceOptionsStatement::class, $statement);
        self::assertEquals([new QualifiedName(['seq_page_cost'])], $statement->withOrigin($statement->origin)->names);
    }

    public function testWithNameReplacesTheTablespace(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLESPACE fast RESET (seq_page_cost)');
        self::assertInstanceOf(ResetTablespaceOptionsStatement::class, $statement);
        self::assertSame('slow', $statement->withName('slow')->name);
    }

    public function testWithNamesReplacesTheRemovedParameters(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLESPACE fast RESET (seq_page_cost)');
        self::assertInstanceOf(ResetTablespaceOptionsStatement::class, $statement);
        self::assertSame('ALTER TABLESPACE "fast" RESET("x"."y")', $statement->withNames([new QualifiedName(['x', 'y'])])->toString());
    }
}
