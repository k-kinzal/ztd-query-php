<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Prepared\DeallocateAllStatement;
use SqlSemantics\Model\Statement\Prepared\DeallocateStatement;
use SqlSemantics\Model\Statement\Prepared\ExecuteQueryStatement;
use SqlSemantics\Model\Statement\Prepared\ExecuteUsingStatement;
use SqlSemantics\Model\Statement\Prepared\PrepareQueryStatement;
use SqlSemantics\Model\Statement\Prepared\PrepareTextStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\PreparedStatements;

#[CoversClass(PreparedStatements::class)]
#[Medium]
final class PreparedStatementsTest extends TestCase
{
    /**
     * @param class-string<DeallocateAllStatement|DeallocateStatement|ExecuteQueryStatement|ExecuteUsingStatement|PrepareQueryStatement|PrepareTextStatement> $class
     */
    #[TestWith([Dialect::PostgreSql, 'PREPARE s(int, text) AS SELECT $1', 'PREPARE "s"(integer, text) AS SELECT $1', PrepareQueryStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'PREPARE s AS SELECT 1', 'PREPARE "s" AS SELECT 1', PrepareQueryStatement::class])]
    #[TestWith([Dialect::MySql, 'PREPARE s FROM @sql', 'PREPARE `s` FROM @`sql`', PrepareTextStatement::class])]
    #[TestWith([Dialect::MySql, "PREPARE s FROM 'SELECT 1'", "PREPARE `s` FROM 'SELECT 1'", PrepareTextStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'EXECUTE s(1, 2)', 'EXECUTE "s"(1, 2)', ExecuteQueryStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'EXECUTE s', 'EXECUTE "s"', ExecuteQueryStatement::class])]
    #[TestWith([Dialect::MySql, 'EXECUTE s USING @x, @y', 'EXECUTE `s` USING @`x`, @`y`', ExecuteUsingStatement::class])]
    #[TestWith([Dialect::MySql, 'EXECUTE s', 'EXECUTE `s`', ExecuteUsingStatement::class])]
    #[TestWith([Dialect::MySql, 'DROP PREPARE s', 'DEALLOCATE PREPARE `s`', DeallocateStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'DEALLOCATE s', 'DEALLOCATE "s"', DeallocateStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'DEALLOCATE ALL', 'DEALLOCATE ALL', DeallocateAllStatement::class])]
    public function testWriteSerializesEachPreparedOperationFromItsOperands(Dialect $dialect, string $sql, string $expected, string $class): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $statement = $binder->bind($sql, strict: false);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, $statement->toString());
        $rebound = $binder->bind($expected, strict: false);
        self::assertInstanceOf($class, $rebound);
        self::assertSame($expected, $rebound->toString());
    }

    public function testWriteKeepsParameterTypesOfAReboundPreparedQuery(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('PREPARE s(int, text) AS SELECT $1');
        self::assertInstanceOf(PrepareQueryStatement::class, $statement);
        $rebound = $binder->bind(PreparedStatements::write($statement)->toString());
        self::assertInstanceOf(PrepareQueryStatement::class, $rebound);
        self::assertSame(['integer', 'text'], array_map(static fn ($type): string => $type->name, $rebound->parameterTypes));
        self::assertSame('s', $rebound->name);
    }
}
