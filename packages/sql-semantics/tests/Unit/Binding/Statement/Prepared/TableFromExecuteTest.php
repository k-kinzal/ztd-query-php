<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Prepared;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Prepared\TableFromExecute;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Prepared\CreateTableFromExecuteStatement;
use SqlSemantics\Model\Statement\Prepared\ExecuteQueryStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableFromExecute::class)]
#[Medium]
final class TableFromExecuteTest extends TestCase
{
    public function testBindReadsTheTableHeadAndTheArguments(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('CREATE TEMP TABLE IF NOT EXISTS copied (a) ON COMMIT DROP AS EXECUTE fetch_rows(1 + 2) WITH DATA', strict: false);
        self::assertInstanceOf(CreateTableFromExecuteStatement::class, $statement);
        self::assertSame([['copied'], ['a'], true, true], [$statement->name->parts, $statement->columns, $statement->ifNotExists, $statement->withData]);
        self::assertSame('(1 + 2)', $statement->arguments[0]->structure()->toString());
    }

    public function testBindLeavesAPlainExecuteToThePreparedBinder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('EXECUTE fetch_rows(1)', strict: false);
        self::assertInstanceOf(ExecuteQueryStatement::class, $statement);
    }
}
