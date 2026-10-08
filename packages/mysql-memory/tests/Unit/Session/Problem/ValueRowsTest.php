<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem;

use MySqlMemory\Error\QueryError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Problem\ValueRows;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;

#[CoversClass(ValueRows::class)]
#[Small]
final class ValueRowsTest extends TestCase
{
    public function testExtendedAddsTheFirstLaterRowOfAnotherLengthIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $statement = $session->analyze('INSERT INTO t VALUES (1), (2, 3), (4)')->statement;
        $error = (new ValueRows())->extended(QueryError::WrongValueCountOnRow->error(1), $statement, GrammarRelease::MySql5744);

        self::assertSame([[1136, 'Column count doesn\'t match value count at row 2']], $error->following);
        self::assertSame([], (new ValueRows())->extended(QueryError::WrongValueCountOnRow->error(1), $statement, GrammarRelease::MySql847)->following);
    }

    public function testExtendedRecordsTheErrorsOfARunIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $session->run('INSERT INTO t (zz) VALUES (1), (2, 3)');

        self::assertSame([['Error', 1054, "Unknown column 'zz' in 'field list'"], ['Error', 1136, "Column count doesn't match value count at row 2"]], $session->diagnostics->conditions);
    }
}
