<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Session\ExecutionBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ExecutionBinder::class)]
#[Medium]
final class ExecutionBinderTest extends TestCase
{
    /**
     * @param class-string<\SqlSemantics\Model\BoundStatement> $class
     */
    #[TestWith([Dialect::PostgreSql, 'CHECKPOINT', \SqlSemantics\Model\Statement\Server\CheckpointStatement::class])]
    #[TestWith([Dialect::MySql, 'KILL 42', \SqlSemantics\Model\Statement\Server\KillConnectionStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'DEALLOCATE ALL', \SqlSemantics\Model\Statement\Prepared\DeallocateAllStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'CLOSE ALL', \SqlSemantics\Model\Statement\Cursor\CloseAllCursorsStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'EXPLAIN SELECT 1', \SqlSemantics\Model\Statement\Plan\ExplainStatement::class])]
    #[TestWith([Dialect::MySql, "XA START 'x'", \SqlSemantics\Model\Statement\Transaction\Xa\XaStartStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'BEGIN', \SqlSemantics\Model\Statement\Transaction\BeginTransactionStatement::class])]
    #[TestWith([Dialect::MySql, 'USE db', \SqlSemantics\Model\Statement\Maintenance\UseDatabaseStatement::class])]
    #[TestWith([Dialect::MySql, 'DO 1', \SqlSemantics\Model\Statement\Execution\DoExpressionsStatement::class])]
    #[TestWith([Dialect::MySql, 'UNLOCK TABLES', \SqlSemantics\Model\Statement\Server\UnlockTablesStatement::class])]
    public function testBindRoutesEachCommandFamilyToItsBinder(Dialect $dialect, string $sql, string $class): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql);
        self::assertInstanceOf($class, $statement);
        self::assertSame($dialect, $statement->origin->dialect);
    }

    public function testBindRoutesTableCommandsThroughTheQueryContext(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)'));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\TruncateTableStatement::class, $binder->bind('TRUNCATE TABLE t'));
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Maintenance\MySql\CheckTablesStatement::class, $binder->bind('CHECK TABLE t'));
    }
}
