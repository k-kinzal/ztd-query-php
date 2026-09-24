<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\PostgreSql\Tablespace;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Tablespace\SetTablespaceOptionsStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetTablespaceOptionsStatement::class)]
#[Medium]
final class SetTablespaceOptionsStatementTest extends TestCase
{
    public function testWithOriginRetainsTheOverrides(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLESPACE fast SET (seq_page_cost = 0.5)');
        self::assertInstanceOf(SetTablespaceOptionsStatement::class, $statement);
        self::assertEquals($statement->parameters, $statement->withOrigin($statement->origin)->parameters);
    }

    public function testWithNameReplacesTheTablespace(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLESPACE fast SET (seq_page_cost = 0.5)');
        self::assertInstanceOf(SetTablespaceOptionsStatement::class, $statement);
        self::assertSame('ALTER TABLESPACE "slow" SET ("seq_page_cost" = 0.5)', $statement->withName('slow')->toString());
    }

    public function testWithParametersReplacesTheOverrides(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('ALTER TABLESPACE fast SET (seq_page_cost = 0.5, random_page_cost = 2)');
        self::assertInstanceOf(SetTablespaceOptionsStatement::class, $statement);
        self::assertCount(1, $statement->withParameters([$statement->parameters[1]])->parameters);
    }
}
