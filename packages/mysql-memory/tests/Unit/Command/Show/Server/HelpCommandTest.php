<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show\Server;

use MySqlMemory\Command\Show\Server\HelpCommand;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(HelpCommand::class)]
#[Small]
final class HelpCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new HelpCommand())->clearsDiagnostics());
    }

    public function testExecuteFindsNoTopic(): void
    {
        $result = (new Instance())->connect()->query('HELP _sffIWaaaaaaaaaaaaaaaaaaaaaaaaaaaaa')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
        self::assertSame([['name', 256], ['is_it_category', 4]], [[$result->columns[0]->name, $result->columns[0]->length], [$result->columns[1]->name, $result->columns[1]->length]]);
    }
}
