<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\StubMysqliResult;
use ZtdQuery\Adapter\Mysqli\MysqliResultStatement;
use ZtdQuery\Connection\StatementInterface;

#[CoversClass(MysqliResultStatement::class)]
final class MysqliResultStatementTest extends TestCase
{
    public function testImplementsStatementInterface(): void
    {
        $stmt = new MysqliResultStatement(null, 0);

        self::assertInstanceOf(StatementInterface::class, $stmt);
    }

    public function testExecuteAlwaysReturnsTrue(): void
    {
        $stmt = new MysqliResultStatement(null, 0);

        self::assertTrue($stmt->execute());
        self::assertTrue($stmt->execute([1, 2, 3]));
    }

    public function testFetchAllReturnsEmptyArrayWhenResultIsNull(): void
    {
        $stmt = new MysqliResultStatement(null, 0);

        self::assertSame([], $stmt->fetchAll());
    }

    public function testFetchAllReturnsRowsFromResult(): void
    {
        $expected = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $result = StubMysqliResult::create($expected);

        $stmt = new MysqliResultStatement($result, 2);

        self::assertSame($expected, $stmt->fetchAll());
    }

    public function testRowCountReturnsAffectedRows(): void
    {
        $stmt = new MysqliResultStatement(null, 5);

        self::assertSame(5, $stmt->rowCount());
    }

    public function testRowCountReturnsZeroForNoAffectedRows(): void
    {
        $stmt = new MysqliResultStatement(null, 0);

        self::assertSame(0, $stmt->rowCount());
    }
}
