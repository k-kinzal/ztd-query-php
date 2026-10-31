<?php

declare(strict_types=1);

namespace Tests\Integration;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Medium]
final class StackedDiagnosticsIsolationTest extends TestCase
{
    public function testFailedStackedReadPreservesTheHandledConditions(): void
    {
        $session = (new Instance('8.4.7', databases: ['d']))->connect(database: 'd');
        $session->query("CREATE PROCEDURE p() BEGIN DECLARE CONTINUE HANDLER FOR SQLEXCEPTION BEGIN GET STACKED DIAGNOSTICS @before = NUMBER; GET STACKED DIAGNOSTICS CONDITION 0 @unused = MESSAGE_TEXT; GET STACKED DIAGNOSTICS @after = NUMBER; GET CURRENT DIAGNOSTICS @current = NUMBER; END; SIGNAL SQLSTATE '45000'; END");
        $session->query('CALL p()');
        $reply = $session->query('SELECT @before, @after, @current')[0];

        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([['1', '1', '2']], $reply->rows);
    }
}
