<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Admin;

use MySqlMemory\Command\Admin\PluginCommand;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PluginCommand::class)]
#[Small]
final class PluginCommandTest extends TestCase
{
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
