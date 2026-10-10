<?php

declare(strict_types=1);

namespace Tests\Unit\Program;

use MySqlMemory\Instance;
use MySqlMemory\Program\Cursors;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cursors::class)]
#[Small]
final class CursorsTest extends TestCase
{
    public function testRunReadsTheRowsOfACursorUntilNotFound(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $session->query('INSERT INTO t VALUES (2), (1), (3)');
        $session->query("CREATE PROCEDURE p() BEGIN DECLARE done INT DEFAULT 0; DECLARE x INT; DECLARE s VARCHAR(50) DEFAULT ''; DECLARE c CURSOR FOR SELECT a FROM t ORDER BY a; DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1; OPEN c; r: LOOP FETCH c INTO x; IF done THEN LEAVE r; END IF; SET s = CONCAT(s, x, ','); END LOOP; CLOSE c; SELECT s; END");

        $result1 = $session->query('CALL p()')[0];
        self::assertInstanceOf(\MySqlMemory\Result\ResultSet::class, $result1);
        self::assertSame([['1,2,3,']], $result1->rows);
    }

    public function testCursorRefusesACursorThatIsNotOpen(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE c CURSOR FOR SELECT 1; FETCH c INTO x; END');

        $this->expectExceptionCode(1326);
        $this->expectExceptionMessage('Cursor is not open');

        $session->query('CALL p()');
    }

    public function testOpenRefusesACursorOpenAlready(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p() BEGIN DECLARE c CURSOR FOR SELECT 1; OPEN c; OPEN c; END');

        $this->expectExceptionCode(1325);
        $this->expectExceptionMessage('Cursor is already open');

        $session->query('CALL p()');
    }

    public function testFetchPastTheLastRowRaisesNoData(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p() BEGIN DECLARE x INT; DECLARE c CURSOR FOR SELECT 1; OPEN c; FETCH c INTO x; FETCH c INTO x; END');

        $this->expectExceptionCode(1329);
        $this->expectExceptionMessage('No data - zero rows fetched, selected, or processed');

        $session->query('CALL p()');
    }

    public function testFetchRefusesAnotherNumberOfVariables(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE PROCEDURE p() BEGIN DECLARE x, y INT; DECLARE c CURSOR FOR SELECT 1; OPEN c; FETCH c INTO x, y; END');

        $this->expectExceptionCode(1328);
        $this->expectExceptionMessage('Incorrect number of FETCH variables');

        $session->query('CALL p()');
    }
}
