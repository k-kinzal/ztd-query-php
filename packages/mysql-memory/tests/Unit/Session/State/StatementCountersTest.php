<?php

declare(strict_types=1);

namespace Tests\Unit\Session\State;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\State\StatementCounters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatementCounters::class)]
#[Small]
final class StatementCountersTest extends TestCase
{
    public function testReceivedCountsRequestsThatCannotBeParsed(): void
    {
        $session = (new Instance())->connect();
        $session->run('SELEC 1');
        $result = $session->query("SHOW STATUS WHERE Variable_name IN ('Questions','Queries','Com_select','Com_show_status')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['Com_select', '0'], ['Com_show_status', '1'], ['Queries', '2'], ['Questions', '2']], $result->rows);
    }

    public function testQueryIncludesProgramInstructionsWithoutAddingClientQuestions(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE PROCEDURE p() BEGIN DECLARE a INT DEFAULT 0; WHILE a<3 DO SET a=a+1; END WHILE; END');
        $before = $session->instance->registry->status->read('Queries');
        $session->query('CALL p()');

        self::assertSame(9, $session->instance->registry->status->read('Queries') - $before);
        self::assertSame(2, $session->instance->registry->status->read('Questions', $session->id));
        self::assertSame(0, $session->instance->registry->status->read('Com_set_option', $session->id));
    }

    public function testEvaluatedCountsResolvingErrorsButNotEarlyParsingErrors(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->run('SELECT * FROM missing');
        $session->run('SET nonexistent_status_option=1');

        self::assertSame(1, $session->instance->registry->status->read('Com_select', $session->id));
        self::assertSame(0, $session->instance->registry->status->read('Com_set_option', $session->id));
    }

    public function testCommandRetainsGlobalCountsAcrossFlushDisconnectAndReset(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $other = $instance->connect();
        $session->query('SELECT 1; SELECT 2; FLUSH STATUS');
        $other->query('SELECT 3');
        $other->close();

        self::assertSame(0, $instance->registry->status->read('Com_select', $session->id));
        self::assertSame(3, $instance->registry->status->read('Com_select'));
        $instance->restart();
        self::assertSame(0, $instance->registry->status->read('Com_select'));
    }
}
