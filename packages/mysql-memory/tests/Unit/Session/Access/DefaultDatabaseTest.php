<?php

declare(strict_types=1);

namespace Tests\Unit\Session\Access;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Session\Access\DefaultDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefaultDatabase::class)]
#[Small]
final class DefaultDatabaseTest extends TestCase
{
    public function testReferencedIgnoresAnUnusedCommonTableBody(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('WITH q AS (SELECT 1 FROM missing) SELECT 1');

        (new DefaultDatabase())->referenced($operation, $session);

        self::assertSame('', $session->variables->database);
    }

    public function testReferencedRejectsAReachedTableBeforeHintWarnings(): void
    {
        $session = (new Instance())->connect();
        $session->run('SELECT /*+ RESOURCE_GROUP(missing) */ * FROM missing');

        self::assertSame([['Error', 1046, 'No database selected']], $session->diagnostics->conditions);
    }

    public function testCheckRequiresTheDeleteTargetDatabaseBeforeDerivedQueryProblems(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        $session->query('DELETE x FROM (SELECT HIGH_PRIORITY 1) AS d');
    }

    public function testCheckRequiresTheEventDatabaseBeforeItsBody(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1046);
        $session->query('CREATE EVENT e ON SCHEDULE EVERY 1 DAY DO SIGNAL c');
    }

    public function testCheckKeepsQualifiedNamesIndependentOfTheDefaultDatabase(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();
        $session->query('CREATE TABLE d.t(a INT); INSERT INTO d.t VALUES (1)');

        $reply = $session->query('DELETE d.t FROM d.t')[0];

        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $reply);
        self::assertSame(1, $reply->affectedRows);
    }

    public function testCheckLeavesStoredProgramMembersDeferred(): void
    {
        $session = (new Instance('8.4.7', [], ['d']))->connect();

        self::assertInstanceOf(\MySqlMemory\Result\Completion::class, $session->query('CREATE PROCEDURE d.p() DELETE t FROM d.t')[0]);
    }
    public function testCheckRequiresTheLegacyLoadDatabaseBeforeItsCharset(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $this->expectExceptionCode(1046);
        $this->expectExceptionMessage('No database selected');

        $session->query("LOAD XML INFILE 'missing' INTO TABLE t CHARSET ``");
    }

}
