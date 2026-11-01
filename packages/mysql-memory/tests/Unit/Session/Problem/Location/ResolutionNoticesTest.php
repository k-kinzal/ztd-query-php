<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Problem\Location;

use MySqlMemory\Instance;
use MySqlMemory\Session\Problem\Location\ResolutionNotices;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResolutionNotices::class)]
#[Small]
final class ResolutionNoticesTest extends TestCase
{
    public function testReportStopsAtTheFirstFailedOperandAfterTablesOpen(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $session->run('SELECT missing, VALUES(a) FROM t');
        self::assertSame([1054], array_column($session->diagnostics->conditions, 1));
        $session->run('SELECT BINARY VALUES(a), missing FROM t');
        self::assertSame([1287, 1681, 1054], array_column($session->diagnostics->conditions, 1));
        $session->run('SELECT VALUES(a), (SELECT 1 FROM absent) FROM t');
        self::assertSame([1146], array_column($session->diagnostics->conditions, 1));
    }

    public function testFreshDistinguishesCachedRoutinePreparationFromChangedRelations(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t(a INT)');
        $session->query('CREATE PROCEDURE p() SELECT VALUES(a) FROM t');
        self::assertSame([], $session->diagnostics->conditions);
        $session->query('CALL p()');
        self::assertSame([1681], array_column($session->diagnostics->conditions, 1));
        $session->query('CALL p()');
        self::assertSame([], $session->diagnostics->conditions);
        $session->query('ALTER TABLE t ADD COLUMN b INT');
        $session->query('CALL p()');
        self::assertSame([1681], array_column($session->diagnostics->conditions, 1));
    }
}
