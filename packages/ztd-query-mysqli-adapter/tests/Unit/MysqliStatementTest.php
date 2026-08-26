<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\FakeConnectionProperties;
use Tests\Fixtures\StubMysqli;
use Tests\Fixtures\StubMysqliField;
use Tests\Fixtures\StubMysqliResult;
use Tests\Fixtures\StubMysqliStmt;
use ZtdQuery\Adapter\Mysqli\ConnectionState;
use ZtdQuery\Adapter\Mysqli\MysqliProperties;
use ZtdQuery\Adapter\Mysqli\MysqliResultColumnExtractor;
use ZtdQuery\Adapter\Mysqli\MysqliStatement;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\ResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversClass(MysqliStatement::class)]
#[UsesClass(ConnectionState::class)]
#[UsesClass(MysqliProperties::class)]
#[UsesClass(MysqliResultColumnExtractor::class)]
final class MysqliStatementTest extends TestCase
{
    public function testItIsTheStatementZtdReadsResultsThrough(): void
    {
        $statement = new MysqliStatement(StubMysqliStmt::create(), new StubMysqli());

        self::assertContains(StatementInterface::class, class_implements($statement));
    }

    public function testResultColumnsLoadPreparedResultMetadata(): void
    {
        $field = new StubMysqliField('id', MYSQLI_TYPE_LONG, '63');
        $stmt = StubMysqliStmt::create();
        $stmt->getResultReturn = StubMysqliResult::create([], [$field]);

        $resolver = self::createStub(ResultColumnTypeResolver::class);
        $resolver->method('resolve')->willReturn(new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'INTEGER'));
        $columns = (new MysqliStatement($stmt, new StubMysqli()))->resultColumns($resolver);

        self::assertSame('id', $columns[0]->name);
        self::assertSame(ColumnTypeFamily::INTEGER, $columns[0]->type->family);
    }
    public function testExecuteRunsTheStatementTheDriverPrepared(): void
    {
        $stmt = StubMysqliStmt::create();
        $statement = new MysqliStatement($stmt, new StubMysqli(), new FakeConnectionProperties());

        self::assertSame([true, 1, null], [$statement->execute(), $stmt->executeCallCount, $stmt->executeCalledWithParams]);
    }

    public function testExecuteBindsTheParametersItIsGiven(): void
    {
        $stmt = StubMysqliStmt::create();
        $statement = new MysqliStatement($stmt, new StubMysqli(), new FakeConnectionProperties());

        $statement->execute([1, 'ada']);

        self::assertSame([1, 'ada'], $stmt->executeCalledWithParams);
    }

    public function testExecuteRaisesWhatTheDriverSaidAboutAStatementItRefused(): void
    {
        $stmt = StubMysqliStmt::create();
        $stmt->executeReturn = false;
        $statement = new MysqliStatement($stmt, new StubMysqli(), new FakeConnectionProperties([
            'errno' => 1064,
            'error' => 'You have an error in your SQL syntax',
        ]));

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('You have an error in your SQL syntax');

        $statement->execute();
    }

    public function testExecuteRaisesWhatTheDriverSaidAboutParametersItRefused(): void
    {
        $stmt = StubMysqliStmt::create();
        $stmt->executeReturn = false;
        $statement = new MysqliStatement($stmt, new StubMysqli(), new FakeConnectionProperties([
            'errno' => 1264,
            'error' => 'Out of range value',
        ]));

        $this->expectExceptionMessage('Out of range value');

        $statement->execute([1]);
    }

    public function testExecuteAnswersFalseWhereTheStatementDidNotRunAndTheDriverSaidNothing(): void
    {
        $stmt = StubMysqliStmt::create();
        $stmt->executeReturn = false;
        $statement = new MysqliStatement($stmt, new StubMysqli(), new FakeConnectionProperties());

        self::assertFalse($statement->execute());
    }

    public function testFetchAllAnswersEveryRowTheStatementHeldAndClosesIt(): void
    {
        $stmt = StubMysqliStmt::create();
        $stmt->getResultReturn = StubMysqliResult::create([['id' => 1], ['id' => 2]]);
        $statement = new MysqliStatement($stmt, new StubMysqli(), new FakeConnectionProperties());

        self::assertSame([[['id' => 1], ['id' => 2]], true], [$statement->fetchAll(), $stmt->closeCalled]);
    }

    public function testFetchAllAnswersNothingWhereTheStatementHeldNoResult(): void
    {
        $stmt = StubMysqliStmt::create();
        $statement = new MysqliStatement($stmt, new StubMysqli(), new FakeConnectionProperties());

        self::assertSame([[], true], [$statement->fetchAll(), $stmt->closeCalled]);
    }

    public function testLoadResultAnswersTheResultTheStatementHolds(): void
    {
        $result = StubMysqliResult::create([['id' => 1]]);
        $stmt = StubMysqliStmt::create();
        $stmt->getResultReturn = $result;
        $statement = new MysqliStatement($stmt, new StubMysqli(), new FakeConnectionProperties());

        self::assertSame($result, $statement->loadResult());
    }

    public function testLoadResultAsksTheStatementForItsResultOnlyOnce(): void
    {
        $result = StubMysqliResult::create([['id' => 1]]);
        $stmt = StubMysqliStmt::create();
        $stmt->getResultReturn = $result;
        $statement = new MysqliStatement($stmt, new StubMysqli(), new FakeConnectionProperties());
        $statement->loadResult();
        $stmt->getResultReturn = false;

        self::assertSame($result, $statement->loadResult());
    }

    public function testRowCountAnswersHowManyRowsTheStatementAffected(): void
    {
        $statement = new MysqliStatement(
            StubMysqliStmt::create(),
            new StubMysqli(),
            new FakeConnectionProperties(['affected_rows' => 7]),
        );

        self::assertSame(7, $statement->rowCount());
    }

    public function testResultColumnsAnswerNothingWhereTheStatementHeldNoResult(): void
    {
        $statement = new MysqliStatement(StubMysqliStmt::create(), new StubMysqli(), new FakeConnectionProperties());

        self::assertSame([], $statement->resultColumns(self::createStub(ResultColumnTypeResolver::class)));
    }
}
