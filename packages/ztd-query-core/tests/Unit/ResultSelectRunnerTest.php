<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeStatement;
use ZtdQuery\ResultSelectRunner;

#[CoversClass(ResultSelectRunner::class)]
final class ResultSelectRunnerTest extends TestCase
{
    public function testRunReturnsRowsFromExecutor(): void
    {
        $runner = new ResultSelectRunner();
        $rows = [['id' => 1, 'name' => 'Alice']];

        $result = $runner->run('SELECT * FROM users', fn () => new FakeStatement($rows));

        self::assertSame($rows, $result);
    }

    public function testRunReturnsEmptyWhenExecutorReturnsFalse(): void
    {
        $runner = new ResultSelectRunner();

        $result = $runner->run('SELECT * FROM users', fn () => false);

        self::assertSame([], $result);
    }

    public function testRunStatementReturnsRows(): void
    {
        $runner = new ResultSelectRunner();
        $rows = [['id' => 1]];
        $statement = new FakeStatement($rows);

        $result = $runner->runStatement($statement);

        self::assertSame($rows, $result);
    }
}
