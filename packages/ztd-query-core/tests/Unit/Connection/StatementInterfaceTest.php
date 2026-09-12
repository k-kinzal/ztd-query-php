<?php

declare(strict_types=1);

namespace Tests\Unit\Connection;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeStatement;
use ZtdQuery\Connection\ResultColumn;
use ZtdQuery\Platform\MissingResultColumnTypeResolver;
use ZtdQuery\Schema\ColumnDeclaration;
use ZtdQuery\Schema\ColumnTypeFamily;

#[CoversNothing]
final class StatementInterfaceTest extends TestCase
{
    public function testExecuteReportsWhetherTheStatementRan(): void
    {
        self::assertTrue((new FakeStatement([]))->execute());
    }

    public function testExecuteTakesTheValuesToBindOrNoneAtAll(): void
    {
        $statement = new FakeStatement([]);

        self::assertTrue($statement->execute(['id' => 1, 0 => null]));
        self::assertTrue($statement->execute());
    }

    public function testFetchAllAnswersEveryRowAsAMapOfColumnToValue(): void
    {
        $statement = new FakeStatement([['id' => 1, 'name' => 'a'], ['id' => 2, 'name' => null]]);

        self::assertSame([['id' => 1, 'name' => 'a'], ['id' => 2, 'name' => null]], $statement->fetchAll());
    }

    public function testFetchAllAnswersNothingForAResultWithNoRows(): void
    {
        self::assertSame([], (new FakeStatement([]))->fetchAll());
    }

    public function testResultColumnsRemainAvailableForAResultWithNoRows(): void
    {
        $columns = [new ResultColumn('id', new ColumnDeclaration(ColumnTypeFamily::INTEGER, 'int4'))];
        $statement = new FakeStatement([], $columns);

        self::assertSame($columns, $statement->resultColumns(new MissingResultColumnTypeResolver()));
    }

    public function testRowCountAnswersHowManyRowsThereAre(): void
    {
        self::assertSame(2, (new FakeStatement([['id' => 1], ['id' => 2]]))->rowCount());
        self::assertSame(0, (new FakeStatement([]))->rowCount());
    }
}
