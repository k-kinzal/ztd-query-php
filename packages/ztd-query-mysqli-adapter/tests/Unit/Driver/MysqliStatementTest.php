<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\StubMysqli;
use Tests\Fixtures\StubMysqliField;
use Tests\Fixtures\StubMysqliResult;
use Tests\Fixtures\StubMysqliStmt;
use ZtdQuery\Adapter\Mysqli\MysqliResultColumnExtractor;
use ZtdQuery\Adapter\Mysqli\MysqliStatement;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnType;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MysqliStatement::class)]
#[UsesClass(MysqliResultColumnExtractor::class)]
final class MysqliStatementTest extends TestCase
{
    public function testExecuteDelegatesToTheNativeStatement(): void
    {
        $stmt = StubMysqliStmt::create();
        $mysqli = new StubMysqli();

        $statement = new MysqliStatement($stmt, $mysqli);

        self::assertTrue($statement->execute(['Alice']));
        self::assertSame(['Alice'], $stmt->executeCalledWithParams);
        self::assertSame(1, $stmt->executeCallCount);
    }

    public function testResultColumnsLoadPreparedResultMetadata(): void
    {
        $field = new StubMysqliField('id', MYSQLI_TYPE_LONG, '63');
        $stmt = StubMysqliStmt::create();
        $stmt->getResultReturn = StubMysqliResult::create([], [$field]);

        $resolver = self::createStub(ResultColumnTypeResolver::class);
        $resolver->method('resolve')->willReturn(new ColumnType(ColumnTypeFamily::INTEGER, 'INTEGER'));
        $columns = (new MysqliStatement($stmt, new StubMysqli()))->resultColumns($resolver);

        self::assertSame('id', $columns[0]->name);
        self::assertSame(ColumnTypeFamily::INTEGER, $columns[0]->type->family);
    }

    public function testFetchAllReturnsThePreparedRowsAndClosesTheStatement(): void
    {
        $native = StubMysqliStmt::create();
        $native->getResultReturn = StubMysqliResult::create([['id' => 7], ['id' => 9]]);
        $statement = new MysqliStatement($native, new StubMysqli());
        self::assertSame([['id' => 7], ['id' => 9]], $statement->fetchAll());
        self::assertTrue($native->closeCalled);
    }

    public function testFetchAllWithoutAResultClosesTheStatement(): void
    {
        $native = StubMysqliStmt::create();
        $statement = new MysqliStatement($native, new StubMysqli());
        self::assertSame([], $statement->fetchAll());
        self::assertTrue($native->closeCalled);
    }

    public function testRowCountMatchesTheNativeStatement(): void
    {
        $native = StubMysqliStmt::create();
        $statement = new MysqliStatement($native, new StubMysqli());
        self::assertSame((int) $native->affected_rows, $statement->rowCount());
    }

    public function testExecutePropagatesFalseAndPreservesParameters(): void
    {
        $native = StubMysqliStmt::create();
        $native->executeReturn = false;
        $statement = new MysqliStatement($native, new StubMysqli());
        self::assertFalse($statement->execute(['Alice']));
        self::assertSame(['Alice'], $native->executeCalledWithParams);
    }

    public function testExecuteOmitsAnEmptyParameterList(): void
    {
        $native = StubMysqliStmt::create();
        $statement = new MysqliStatement($native, new StubMysqli());
        self::assertTrue($statement->execute([]));
        self::assertNull($native->executeCalledWithParams);
    }
    public function testExecuteWithoutParametersPropagatesNativeFalse(): void
    {
        $native = StubMysqliStmt::create();
        $native->executeReturn = false;
        self::assertFalse((new MysqliStatement($native, new StubMysqli()))->execute());
        self::assertNull($native->executeCalledWithParams);
    }

    public function testResultColumnsWithoutANativeResultAreEmpty(): void
    {
        self::assertSame([], (new MysqliStatement(StubMysqliStmt::create(), new StubMysqli()))->resultColumns(self::createStub(ResultColumnTypeResolver::class)));
    }

    public function testFetchReusesMetadataResultAndReleasesIt(): void
    {
        $native = StubMysqliStmt::create();
        $result = StubMysqliResult::create([['id' => 42]], [new StubMysqliField('id', MYSQLI_TYPE_LONG, '63')]);
        $native->getResultReturn = $result;
        $statement = new MysqliStatement($native, new StubMysqli());
        $resolver = self::createStub(ResultColumnTypeResolver::class);
        $resolver->method('resolve')->willReturn(new ColumnType(ColumnTypeFamily::INTEGER, 'INTEGER'));
        self::assertCount(1, $statement->resultColumns($resolver));
        $native->getResultReturn = false;
        self::assertSame([['id' => 42]], $statement->fetchAll());
        self::assertTrue($result->freed);
        self::assertTrue($native->closeCalled);
    }

}
