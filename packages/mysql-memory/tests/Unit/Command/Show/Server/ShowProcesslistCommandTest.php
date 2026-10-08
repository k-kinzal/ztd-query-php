<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show\Server;

use MySqlMemory\Command\Show\Server\ShowProcesslistCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(ShowProcesslistCommand::class)]
#[Small]
final class ShowProcesslistCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowProcesslistCommand())->clearsDiagnostics());
    }

    public function testExecuteListsTheSessionsByConnectionId(): void
    {
        $instance = new Instance();
        $idle = $instance->connect(database: 'mysql');
        $s = $instance->connect();
        $statement = 'SHOW PROCESSLIST /* ' . str_repeat('x', 100) . ' */';

        $result1 = $s->query($statement)[0];
        self::assertInstanceOf(ResultSet::class, $result1);
        self::assertSame([[(string) $idle->id, 'root', 'localhost', 'mysql', 'Sleep', '0', '', null], [(string) $s->id, 'root', 'localhost', null, 'Query', '0', 'init', substr($statement, 0, 100)]], $result1->rows);
        $result2 = $s->query('SHOW WARNINGS')[0];
        self::assertInstanceOf(ResultSet::class, $result2);
        self::assertSame([['Warning', '1287', "'INFORMATION_SCHEMA.PROCESSLIST' is deprecated and will be removed in a future release. Please use performance_schema.processlist instead"]], $result2->rows);
        $result3 = (new Instance('5.7.44'))->connect()->query('SHOW FULL PROCESSLIST')[0];
        self::assertInstanceOf(ResultSet::class, $result3);
        self::assertSame('starting', $result3->rows[0][6]);
    }

    public function testHeadingsDescribesTheColumns(): void
    {
        self::assertSame([22, 8, Field::LongBlob, Field::VarString], [(new ShowProcesslistCommand())->headings(true, GrammarRelease::MySql847)[0]->length, (new ShowProcesslistCommand())->headings(true, GrammarRelease::MySql847)[5]->length, (new ShowProcesslistCommand())->headings(true, GrammarRelease::MySql847)[7]->field, (new ShowProcesslistCommand())->headings(false, GrammarRelease::MySql5744)[7]->field]);
    }
}
