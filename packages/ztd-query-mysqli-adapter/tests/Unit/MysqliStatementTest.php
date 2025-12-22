<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\StubMysqli;
use Tests\Fixtures\StubMysqliStmt;
use ZtdQuery\Adapter\Mysqli\MysqliStatement;
use ZtdQuery\Connection\StatementInterface;

#[CoversClass(MysqliStatement::class)]
final class MysqliStatementTest extends TestCase
{
    public function testImplementsStatementInterface(): void
    {
        $stmt = StubMysqliStmt::create();
        $mysqli = new StubMysqli();

        $statement = new MysqliStatement($stmt, $mysqli);

        self::assertInstanceOf(StatementInterface::class, $statement);
    }
}
