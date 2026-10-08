<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show\Server;

use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Server\ShowProfilesCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Session\ProfileSection;

#[CoversClass(ShowProfilesCommand::class)]
#[Small]
final class ShowProfilesCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowProfilesCommand())->clearsDiagnostics());
    }

    public function testExecuteListsNoProfileAndWarnsOfTheDeprecation(): void
    {
        $session = (new Instance())->connect();

        $result = $session->query('SHOW PROFILES')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([], $result->rows);
        self::assertSame(['Query_ID', 'Duration', 'Query'], [$result->columns[0]->name, $result->columns[1]->name, $result->columns[2]->name]);
        self::assertSame([['Warning', '1287', "'SHOW PROFILES' is deprecated and will be removed in a future release. Please use Performance Schema instead"]], $warnings->rows);
    }

    public function testExecuteAnswersTheColumnsOfTheSections(): void
    {
        $result = (new Instance())->connect()->query('SHOW PROFILE PAGE FAULTS FOR QUERY 0')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['Status', 'Duration', 'Page_faults_major', 'Page_faults_minor'], array_map(static fn ($column): string => $column->name, $result->columns));
        self::assertSame([], $result->rows);
    }

    public function testHeadingsOrderTheColumnsAsTheTable(): void
    {
        self::assertSame(['Status', 'Duration', 'CPU_user', 'CPU_system', 'Source_function', 'Source_file', 'Source_line'], array_map(static fn (Heading $heading): string => $heading->name, (new ShowProfilesCommand())->headings([ProfileSection::Source, ProfileSection::Cpu])));
    }

    public function testExecuteRefusesALimitNamingAVariableBeforeWarning(): void
    {
        $session = (new Instance())->connect();
        $error = $session->run('SHOW PROFILE FOR QUERY 1 LIMIT abc')[0];

        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(SqlError::class, $error);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([1327, 'Undeclared variable: abc'], [$error->getCode(), $error->getMessage()]);
        self::assertSame([['Error', '1327', 'Undeclared variable: abc']], $warnings->rows);
    }
}
