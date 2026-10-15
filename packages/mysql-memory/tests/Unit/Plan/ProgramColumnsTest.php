<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use MySqlMemory\Instance;
use MySqlMemory\Plan\ProgramColumns;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProgramColumns::class)]
#[Small]
final class ProgramColumnsTest extends TestCase
{
    public function testCalledFlagsTheTextAStoredFunctionReturnsAsBlob(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query("CREATE FUNCTION f() RETURNS TEXT DETERMINISTIC RETURN 'x'");
        $session->query("CREATE FUNCTION g() RETURNS VARCHAR(3) DETERMINISTIC RETURN 'x'");

        $result = $session->query('SELECT f(), g()')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([16, 0], [$result->columns[0]->flags, $result->columns[1]->flags]);
    }

    public function testCalledUsesSemanticFunctionClassificationAndPreservesOutputNames(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE FUNCTION d.`COLLATION`(x INT) RETURNS INT DETERMINISTIC RETURN x');
        $session->query('CREATE FUNCTION d.ABS(x INT) RETURNS INT DETERMINISTIC RETURN x');

        $result = $session->query('SELECT `COLLATION`(3) AS alias, (d.ABS(3)) AS parens, ABS(3), d.ABS(3) + 1 AS expr, (SELECT d.ABS(3) AS inner_name) AS outer_name')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['alias', 'parens', '', '', 'inner_name'], array_column($result->columns, 'originalName'));
        self::assertSame([32768, 32768, 32897, 32896, 32768], array_column($result->columns, 'flags'));
    }

    public function testFlaggedGivesAVariableTheFlagsOfAColumnWithoutBinary(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p(a INT) BEGIN DECLARE v INT DEFAULT 3; SELECT a, v, a + 1; END');

        $result = $session->query('CALL p(5)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([32768, 32768, 32896], array_map(static fn ($column): int => $column->flags, $result->columns));
    }
}
