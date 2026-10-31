<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin;

use MySqlMemory\Command\Admin\PluginCommand;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Server\Plugin\UninstallPlugin;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(PluginCommand::class)]
#[Small]
final class PluginCommandTest extends TestCase
{
    #[DataProvider('providerCatalogLocks')]
    public function testLocksPrecedeLibraryLookupAndRetainComponentDiagnostics(string $version, string $lock, string $sql, int $code, string $message, bool $component): void
    {
        $session = (new Instance($version, databases: ['test']))->connect(database: 'test');
        $session->query('CREATE TABLE t (id INT); LOCK TABLES ' . $lock);

        $replies = $session->run($sql);

        self::assertInstanceOf(SqlError::class, $replies[0]);
        self::assertSame($code, $replies[0]->getCode());
        self::assertSame($message, $replies[0]->getMessage());
        $conditions = [['Error', $code, $message]];
        self::assertSame($component ? [...$conditions, ['Error', 3543, 'The mysql.component table is missing or has an incorrect definition.']] : $conditions, $session->diagnostics->conditions);
    }

    /**
     * @return iterable<string, array{string, string, string, int, string, bool}>
     */
    public static function providerCatalogLocks(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.1.0', '8.2.0', '8.3.0', '8.4.7', '9.0.1', '9.1.0'] as $version) {
            $commands = ['plugin' => ["INSTALL PLUGIN missing SONAME 'text'", "INSTALL PLUGIN InnoDB SONAME '/text'", 'UNINSTALL PLUGIN missing', 'UNINSTALL PLUGIN InnoDB']];
            if (!str_starts_with($version, '5.')) {
                $commands['component'] = ["INSTALL COMPONENT 'text'", "INSTALL COMPONENT 'file://text'", "UNINSTALL COMPONENT 'first', 'second'"];
            }
            foreach ($commands as $table => $statements) {
                foreach (['t READ', 'mysql.' . $table . ' READ', 'mysql.' . $table . ' AS x WRITE'] as $lock) {
                    $read = $lock === 'mysql.' . $table . ' READ';
                    foreach ($statements as $sql) {
                        yield $version . '/' . $lock . '/' . $sql => [$version, $lock, $sql, $read ? 1099 : 1100, $read ? "Table '$table' was locked with a READ lock and can't be updated" : "Table '$table' was not locked with LOCK TABLES", $table === 'component'];
                    }
                }
            }
        }
    }

    public function testLocksFollowComponentSettingEvaluation(): void
    {
        $session = (new Instance('8.4.7', databases: ['test']))->connect(database: 'test');
        $session->query('CREATE TABLE t (id INT); LOCK TABLES t READ; SET @v=0');
        $session->run("INSTALL COMPONENT 'missing' SET GLOBAL x=(@v:=@v+1), y=NULL");

        self::assertSame([
            ['Warning', 1287, "Setting user variables within expressions is deprecated and will be removed in a future release. Consider alternatives: 'SET variable=expression, ...', or 'SELECT expression(s) INTO variables(s)'."],
            ['Error', 4160, 'The value supplied for y in the SET list cannot be null'],
        ], $session->diagnostics->conditions);
        self::assertSame(2, $session->variables->user('v')[0]);
    }

    public function testUrnBoundsDiagnosticBytesAndTerminatesAtNul(): void
    {
        $command = new PluginCommand();

        self::assertSame(str_repeat('x', 192), $command->urn(str_repeat('x', 193)));
        self::assertSame(str_repeat('x', 191) . substr('猫', 0, 1), $command->urn(str_repeat('x', 191) . '猫'));
        self::assertSame('first', $command->urn("first\0tail"));
    }

    public function testLoadableReportsTheLibraryWithoutAQualifiedName(): void
    {
        $session = (new Instance())->connect();
        $this->expectExceptionCode(1126);
        $this->expectExceptionMessage("Can't open shared library 'text' (errno: 11 /usr/lib64/mysql/plugin/text: cannot open shared object file: No such file or directory)");

        $session->query("CREATE AGGREGATE FUNCTION f RETURNS REAL SONAME 'text'");
    }

    public function testLoadableRefusesNativeNamesBeforeReadingTheLibrary(): void
    {
        $session = (new Instance())->connect();
        $this->expectExceptionCode(1585);
        $this->expectExceptionMessage("This function 'ABS' has the same name as a native function");

        $session->query("CREATE FUNCTION ABS RETURNS STRING SONAME '/tmp/text'");
    }

    public function testLoadableRefusesKeywordNamesBeforeReadingTheLibrary(): void
    {
        $session = (new Instance())->connect();
        $this->expectExceptionCode(1585);
        $this->expectExceptionMessage("This function 'BEGIN' has the same name as a native function");

        $session->query("CREATE FUNCTION BEGIN RETURNS INT4 SONAME 'a''b'");
    }

    public function testLoadableRefusesIfNotExistsForANativeName(): void
    {
        $session = (new Instance())->connect();
        $this->expectExceptionCode(4101);
        $this->expectExceptionMessage("This function 'ROTATE' has the same name as a native function. The 'IF NOT EXISTS' clause is not supported while creating a loadable function with the same name as a native function.");
        $session->query("CREATE AGGREGATE FUNCTION IF NOT EXISTS ROTATE RETURNS REAL SONAME 'a''b'");
    }

    public function testLoadableRefusesPaths(): void
    {
        $session = (new Instance())->connect();
        $this->expectExceptionCode(1124);

        $session->query("CREATE FUNCTION f RETURNS STRING SONAME '/tmp/text'");
    }

    public function testUnopenedBoundsTheOperatingSystemDetailForFunctions(): void
    {
        $error = (new PluginCommand())->unopened('/usr/lib64/mysql/plugin/', str_repeat('x', 65), '11', false);

        self::assertSame("Can't open shared library '" . str_repeat('x', 65) . "' (errno: 11 /usr/lib64/mysql/plugin/" . str_repeat('x', 65) . ': cannot open shared object file: No su)', $error->getMessage());
    }

    public function testUnopenedBoundsTheOperatingSystemDetailForPlugins(): void
    {
        $library = str_repeat('x', 64);
        $error = (new PluginCommand())->unopened('/usr/lib64/mysql/plugin/', $library);

        self::assertSame("Can't open shared library '/usr/lib64/mysql/plugin/" . $library . "' (errno: 11 /usr/lib64/mysql/plugin/" . $library . ': cannot open shared object file: No suc)', $error->getMessage());
    }

    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new PluginCommand())->clearsDiagnostics());
    }

    public function testExecuteCannotOpenALibrary(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1126);
        $this->expectExceptionMessage("Can't open shared library '/usr/lib64/mysql/plugin/text' (errno: 11 /usr/lib64/mysql/plugin/text: cannot open shared object file: No such file or directory)");

        $session->query("INSTALL PLUGIN p SONAME 'text'");
    }

    public function testExecuteRefusesALibraryWithAPath(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1124);
        $this->expectExceptionMessage('No paths allowed for shared library');

        $session->query("INSTALL PLUGIN p SONAME 'a/p.so'");
    }

    public function testExecuteRefusesALibraryOfMoreThan64Characters(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1124);

        $session->query("INSTALL PLUGIN p SONAME '" . str_repeat('a', 65) . "'");
    }

    public function testExecuteRefusesTheNameOfABuiltInPlugin(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1125);
        $this->expectExceptionMessage("Function 'innodb' already exists");

        $session->query("INSTALL PLUGIN innodb SONAME 'a/b'");
    }

    public function testExecuteCannotUninstallABuiltInPlugin(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1619);
        $this->expectExceptionMessage('Built-in plugins cannot be deleted');

        $session->query('UNINSTALL PLUGIN mysql_native_password');
    }

    public function testExecutePreservesLegacyBuiltinWarningsBeforeTheMissingPluginError(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $node = new UninstallPlugin(new Name('mysql_old_password'));
        $session->text = 'unrelated invalid input';
        $statement1 = $node;
        $parameters1 = [];
        $session->activity->begin();
        StatementCounters::received($session);

        $session->dots = [];
        try {
            $operation1 = (new Binding())->operation($session, $statement1, $parameters1, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, \SqlSemantics\Contract\ParameterStyle::Native));
            $result1 = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1), $parameters1);
        } catch (SqlError $executionError) {
            $result1 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $warnings1 = array_map(static fn (array $condition): array => [$condition[0], (string) $condition[1], $condition[2]], $session->diagnostics->conditions);
        $result = $result1;
        self::assertSame([1305, '42000', 'PLUGIN mysql_old_password does not exist'], $result instanceof SqlError ? [$result->getCode(), $result->sqlState(), $result->getMessage()] : null);
        self::assertSame([['Warning', '1619', 'Built-in plugins cannot be deleted'], ['Error', '1305', 'PLUGIN mysql_old_password does not exist']], $warnings1);
    }

    public function testExecuteCannotUninstallAMissingPlugin(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(1305);
        $this->expectExceptionMessage('PLUGIN RESTART does not exist');

        $session->query('UNINSTALL PLUGIN RESTART');
    }

    public function testExecuteWarnsForEachComponentBeforeFailing(): void
    {
        $session = (new Instance())->connect();
        $session->run("UNINSTALL COMPONENT 'admin_b', 'file://admin_c'");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([
            ['Warning', '3542', "The Persistent Dynamic Loader was used to unload a component 'admin_b', but it was not used to load that component before."],
            ['Warning', '3542', "The Persistent Dynamic Loader was used to unload a component 'file://admin_c', but it was not used to load that component before."],
            ['Error', '3537', "Component specified by URN 'admin_b' to unload has not been loaded before."],
        ], $warnings->rows);
    }

    public function testExecuteChecksTheUrnBeforeTheSettings(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3527);
        $this->expectExceptionMessage("Cannot find schema in specified URN: 'text'.");

        $session->query("INSTALL COMPONENT 'text', 'text' SET _sqlfaker_identifier = ON");
    }

    public function testBuiltInIgnoresLetterCase(): void
    {
        self::assertSame([true, false], [(new PluginCommand())->builtIn('INNODB'), (new PluginCommand())->builtIn('p')]);
    }

    public function testBuiltInUsesTheConfiguredRelease(): void
    {
        $command = new PluginCommand();

        self::assertTrue($command->builtIn('mysql_old_password', '5.6.51'));
        self::assertFalse($command->builtIn('mysql_old_password', '5.7.44'));
        self::assertFalse($command->builtIn('caching_sha2_password', '5.6.51'));
        self::assertFalse($command->builtIn('mysql_native_password', '9.1.0'));
    }

    public function testLoadRefusesASchemeWithoutLoader(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionCode(3528);
        $this->expectExceptionMessage("Cannot acquire scheme load service implementation for schema 'FILE' in specified URN: 'FILE://a'.");

        (new PluginCommand())->load('FILE://a', '/usr/lib64/mysql/plugin/');
    }

    public function testLoadNamesTheLibraryOfAFileUrn(): void
    {
        $session = (new Instance())->connect();
        $session->run("INSTALL COMPONENT 'file://admin_a.so'");
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([
            ['Error', '1126', "Can't open shared library '/usr/lib64/mysql/plugin/admin_a.so.so' (errno: 11 /usr/lib64/mysql/plugin/admin_a.so.so: cannot open shared object file: No such file or directory)"],
            ['Error', '3529', "Cannot load component from specified URN: 'file://admin_a.so'."],
        ], $warnings->rows);
    }

    public function testUnopenedReadsADirectoryForAnEmptyName(): void
    {
        self::assertSame("Can't open shared library '/usr/lib64/mysql/plugin/..' (errno: 11 /usr/lib64/mysql/plugin/..: cannot read file data: Is a directory)", (new PluginCommand())->unopened('/usr/lib64/mysql/plugin/', '..')->getMessage());
    }

    public function testUnopenedReportsTheErrorNumberOfTheRelease(): void
    {
        self::assertSame("Can't open shared library '/p/x.so' (errno: 2 /p/x.so: cannot open shared object file: No such file or directory)", (new PluginCommand())->unopened('/p/', 'x.so', '2')->getMessage());
    }
}
