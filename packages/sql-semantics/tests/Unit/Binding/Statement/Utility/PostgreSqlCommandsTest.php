<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Utility\PostgreSqlCommands;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Database\CreateDatabaseStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PostgreSqlCommands::class)]
#[Medium]
final class PostgreSqlCommandsTest extends TestCase
{
    #[TestWith(['CREATE DATABASE app', CreateDatabaseStatement::class])]
    public function testBindRoutesEachUtilityFamily(string $sql, string $class): void
    {
        self::assertSame($class, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)::class);
    }

    public function testBindLeavesOtherDialectsToTheirBinders(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE DATABASE app');
        self::assertSame(\SqlSemantics\Model\Statement\Definition\MySql\CreateDatabaseStatement::class, $statement::class);
    }
}
