<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\ResultStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ResultStatement::class)]
#[Medium]
final class ResultStatementTest extends TestCase
{
    /**
     * @param list<string> $names
     */
    #[TestWith(['SELECT id, n AS total FROM t', ['id', 'total']])]
    #[TestWith(['TABLE t', ['id', 'n']])]
    #[TestWith(['VALUES (1, 2)', ['column1', 'column2']])]
    #[TestWith(['SELECT id FROM t UNION SELECT n FROM t', ['id']])]
    #[TestWith(['INSERT INTO t(id) VALUES (1) RETURNING id, n', ['id', 'n']])]
    #[TestWith(['UPDATE t SET n=1 RETURNING n', ['n']])]
    #[TestWith(['DELETE FROM t RETURNING id', ['id']])]
    public function testResultColumnsNamesEachOrderedOutput(string $sql, array $names): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER, n INTEGER)')))->bind($sql);
        self::assertInstanceOf(ResultStatement::class, $statement);
        self::assertContainsOnlyInstancesOf(OutputColumn::class, $statement->resultColumns());
        self::assertSame($names, array_column($statement->resultColumns(), 'name'));
        self::assertSame(array_keys($names), array_column($statement->resultColumns(), 'ordinal'));
    }

    public function testResultColumnsIsEmptyForAMutationWithoutReturning(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)')))->bind('DELETE FROM t');
        self::assertInstanceOf(ResultStatement::class, $statement);
        self::assertSame([], $statement->resultColumns());
    }

    #[TestWith(['BEGIN'])]
    #[TestWith(['SET search_path=public'])]
    #[TestWith(['CREATE TABLE u(id INTEGER)'])]
    public function testCommandsWithoutResultsDoNotImplementTheContract(string $sql): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertNotInstanceOf(ResultStatement::class, $statement);
    }
}
