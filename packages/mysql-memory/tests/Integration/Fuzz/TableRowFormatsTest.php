<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use mysqli;
use mysqli_result;
use MySqlMemory\Server\Server;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class TableRowFormatsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerSql(): iterable
    {
        $campaigns = [
            ['CREATE DATABASE rf', 'USE rf', 'SET innodb_strict_mode=1', 'SET GLOBAL innodb_file_format=\'Antelope\'', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=DEFAULT', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=REDUNDANT', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=COMPACT', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=DYNAMIC', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=COMPRESSED', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=FIXED', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'SET innodb_strict_mode=1', 'SET GLOBAL innodb_file_format=\'Barracuda\'', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=DEFAULT', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=REDUNDANT', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=COMPACT', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=DYNAMIC', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=COMPRESSED', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=FIXED', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'SET innodb_strict_mode=0', 'SET GLOBAL innodb_file_format=\'Antelope\'', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=DEFAULT', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=REDUNDANT', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=COMPACT', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=DYNAMIC', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=COMPRESSED', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=FIXED', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'SET innodb_strict_mode=0', 'SET GLOBAL innodb_file_format=\'Barracuda\'', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=DEFAULT', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=REDUNDANT', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=COMPACT', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=DYNAMIC', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=COMPRESSED', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r (a INT, b VARCHAR(50)) ENGINE=InnoDB ROW_FORMAT=FIXED', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SHOW CREATE TABLE r', 'SET innodb_strict_mode=1', 'SET GLOBAL innodb_default_row_format=\'COMPACT\'', 'DROP TABLE IF EXISTS r', 'CREATE TABLE r(a INT) ENGINE=InnoDB', 'SET GLOBAL innodb_default_row_format=\'DYNAMIC\'', 'CREATE TABLE copied LIKE r', 'ALTER TABLE r ADD COLUMN b INT', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' ORDER BY table_name', 'ALTER TABLE r ROW_FORMAT=DEFAULT', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' ORDER BY table_name', 'ALTER TABLE copied FORCE', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' ORDER BY table_name', 'SHOW CREATE TABLE r', 'SHOW CREATE TABLE copied'],
            ['CREATE DATABASE rf', 'USE rf', 'DROP TABLE IF EXISTS r', 'SET GLOBAL innodb_default_row_format=\'COMPACT\'', 'CREATE TABLE r(a INT) ENGINE=InnoDB', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SET GLOBAL innodb_default_row_format=\'DYNAMIC\'', 'ALTER TABLE r ADD COLUMN b INT', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'DROP TABLE IF EXISTS r', 'SET GLOBAL innodb_default_row_format=\'COMPACT\'', 'CREATE TABLE r(a INT) ENGINE=InnoDB', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SET GLOBAL innodb_default_row_format=\'DYNAMIC\'', 'ALTER TABLE r ADD COLUMN b INT, ALGORITHM=INSTANT', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'DROP TABLE IF EXISTS r', 'SET GLOBAL innodb_default_row_format=\'COMPACT\'', 'CREATE TABLE r(a INT) ENGINE=InnoDB', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SET GLOBAL innodb_default_row_format=\'DYNAMIC\'', 'ALTER TABLE r ADD COLUMN b INT, ALGORITHM=INPLACE', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'DROP TABLE IF EXISTS r', 'SET GLOBAL innodb_default_row_format=\'COMPACT\'', 'CREATE TABLE r(a INT) ENGINE=InnoDB', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SET GLOBAL innodb_default_row_format=\'DYNAMIC\'', 'ALTER TABLE r ADD COLUMN b INT, ALGORITHM=COPY', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'DROP TABLE IF EXISTS r', 'SET GLOBAL innodb_default_row_format=\'COMPACT\'', 'CREATE TABLE r(a INT) ENGINE=InnoDB', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SET GLOBAL innodb_default_row_format=\'DYNAMIC\'', 'ALTER TABLE r COMMENT=\'c\'', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'DROP TABLE IF EXISTS r', 'SET GLOBAL innodb_default_row_format=\'COMPACT\'', 'CREATE TABLE r(a INT) ENGINE=InnoDB', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SET GLOBAL innodb_default_row_format=\'DYNAMIC\'', 'ALTER TABLE r ALTER COLUMN a SET DEFAULT 1', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'DROP TABLE IF EXISTS r', 'SET GLOBAL innodb_default_row_format=\'COMPACT\'', 'CREATE TABLE r(a INT) ENGINE=InnoDB', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SET GLOBAL innodb_default_row_format=\'DYNAMIC\'', 'ALTER TABLE r ADD INDEX (a)', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'DROP TABLE IF EXISTS r', 'SET GLOBAL innodb_default_row_format=\'COMPACT\'', 'CREATE TABLE r(a INT) ENGINE=InnoDB', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SET GLOBAL innodb_default_row_format=\'DYNAMIC\'', 'ALTER TABLE r FORCE', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'DROP TABLE IF EXISTS r', 'SET GLOBAL innodb_default_row_format=\'COMPACT\'', 'CREATE TABLE r(a INT) ENGINE=InnoDB', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'SET GLOBAL innodb_default_row_format=\'DYNAMIC\'', 'ALTER TABLE r ROW_FORMAT=DEFAULT', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\'', 'DROP TABLE r', 'SET GLOBAL innodb_default_row_format=\'COMPACT\'', 'CREATE TABLE r(a INT) ENGINE=InnoDB', 'SET GLOBAL innodb_default_row_format=\'DYNAMIC\'', 'RENAME TABLE r TO z', 'RENAME TABLE z TO r', 'SELECT TABLE_NAME, ROW_FORMAT, CREATE_OPTIONS FROM information_schema.tables WHERE table_schema=\'rf\' AND table_name=\'r\''],
        ];
        yield 'row163/5.7.44' => ['5.7.44', ['SET GLOBAL innodb_file_format=\'bad\'', 'SET GLOBAL innodb_file_format=NULL', 'SET innodb_file_format=\'Antelope\'', 'SET GLOBAL innodb_file_format=\'Antelope\'', 'SET GLOBAL innodb_file_format=DEFAULT', 'SELECT @@innodb_file_format', 'SET GLOBAL innodb_file_format_max=\'Antelope\'']];
        yield 'row165/5.7.44' => ['5.7.44', ['SET GLOBAL innodb_file_format=\'bad\'', 'SELECT @@GLOBAL.innodb_file_format', 'SET GLOBAL innodb_file_format=0', 'SELECT @@GLOBAL.innodb_file_format', 'SET GLOBAL innodb_file_format=1', 'SELECT @@GLOBAL.innodb_file_format', 'SET GLOBAL innodb_file_format=2', 'SELECT @@GLOBAL.innodb_file_format', 'SET GLOBAL innodb_file_format=3', 'SELECT @@GLOBAL.innodb_file_format', 'SET GLOBAL innodb_file_format=-1', 'SELECT @@GLOBAL.innodb_file_format', 'SET GLOBAL innodb_file_format=\'compact\'', 'SELECT @@GLOBAL.innodb_file_format', 'SET GLOBAL innodb_file_format=\'barracuda\'', 'SELECT @@GLOBAL.innodb_file_format', 'SET GLOBAL innodb_file_format=1.0', 'SELECT @@GLOBAL.innodb_file_format', 'SET GLOBAL innodb_file_format_max=\'bad\'', 'SELECT @@GLOBAL.innodb_file_format_max', 'SET GLOBAL innodb_file_format_max=0', 'SELECT @@GLOBAL.innodb_file_format_max', 'SET GLOBAL innodb_file_format_max=1', 'SELECT @@GLOBAL.innodb_file_format_max', 'SET GLOBAL innodb_file_format_max=2', 'SELECT @@GLOBAL.innodb_file_format_max', 'SET GLOBAL innodb_file_format_max=3', 'SELECT @@GLOBAL.innodb_file_format_max', 'SET GLOBAL innodb_file_format_max=-1', 'SELECT @@GLOBAL.innodb_file_format_max', 'SET GLOBAL innodb_file_format_max=\'compact\'', 'SELECT @@GLOBAL.innodb_file_format_max', 'SET GLOBAL innodb_file_format_max=\'barracuda\'', 'SELECT @@GLOBAL.innodb_file_format_max', 'SET GLOBAL innodb_file_format_max=1.0', 'SELECT @@GLOBAL.innodb_file_format_max', 'SET GLOBAL innodb_default_row_format=\'bad\'', 'SELECT @@GLOBAL.innodb_default_row_format', 'SET GLOBAL innodb_default_row_format=0', 'SELECT @@GLOBAL.innodb_default_row_format', 'SET GLOBAL innodb_default_row_format=1', 'SELECT @@GLOBAL.innodb_default_row_format', 'SET GLOBAL innodb_default_row_format=2', 'SELECT @@GLOBAL.innodb_default_row_format', 'SET GLOBAL innodb_default_row_format=3', 'SELECT @@GLOBAL.innodb_default_row_format', 'SET GLOBAL innodb_default_row_format=-1', 'SELECT @@GLOBAL.innodb_default_row_format', 'SET GLOBAL innodb_default_row_format=\'compact\'', 'SELECT @@GLOBAL.innodb_default_row_format', 'SET GLOBAL innodb_default_row_format=\'barracuda\'', 'SELECT @@GLOBAL.innodb_default_row_format', 'SET GLOBAL innodb_default_row_format=1.0', 'SELECT @@GLOBAL.innodb_default_row_format']];
        yield 'row165/8.4.7' => ['8.4.7', ['SET GLOBAL innodb_file_format=\'bad\'', 'SELECT @@GLOBAL.innodb_file_format', 'SET GLOBAL innodb_file_format=0', 'SELECT @@GLOBAL.innodb_file_format', 'SET GLOBAL innodb_file_format=1', 'SELECT @@GLOBAL.innodb_file_format', 'SET GLOBAL innodb_file_format=2', 'SELECT @@GLOBAL.innodb_file_format', 'SET GLOBAL innodb_file_format=3', 'SELECT @@GLOBAL.innodb_file_format', 'SET GLOBAL innodb_file_format=-1', 'SELECT @@GLOBAL.innodb_file_format', 'SET GLOBAL innodb_file_format=\'compact\'', 'SELECT @@GLOBAL.innodb_file_format', 'SET GLOBAL innodb_file_format=\'barracuda\'', 'SELECT @@GLOBAL.innodb_file_format', 'SET GLOBAL innodb_file_format=1.0', 'SELECT @@GLOBAL.innodb_file_format', 'SET GLOBAL innodb_file_format_max=\'bad\'', 'SELECT @@GLOBAL.innodb_file_format_max', 'SET GLOBAL innodb_file_format_max=0', 'SELECT @@GLOBAL.innodb_file_format_max', 'SET GLOBAL innodb_file_format_max=1', 'SELECT @@GLOBAL.innodb_file_format_max', 'SET GLOBAL innodb_file_format_max=2', 'SELECT @@GLOBAL.innodb_file_format_max', 'SET GLOBAL innodb_file_format_max=3', 'SELECT @@GLOBAL.innodb_file_format_max', 'SET GLOBAL innodb_file_format_max=-1', 'SELECT @@GLOBAL.innodb_file_format_max', 'SET GLOBAL innodb_file_format_max=\'compact\'', 'SELECT @@GLOBAL.innodb_file_format_max', 'SET GLOBAL innodb_file_format_max=\'barracuda\'', 'SELECT @@GLOBAL.innodb_file_format_max', 'SET GLOBAL innodb_file_format_max=1.0', 'SELECT @@GLOBAL.innodb_file_format_max', 'SET GLOBAL innodb_default_row_format=\'bad\'', 'SELECT @@GLOBAL.innodb_default_row_format', 'SET GLOBAL innodb_default_row_format=0', 'SELECT @@GLOBAL.innodb_default_row_format', 'SET GLOBAL innodb_default_row_format=1', 'SELECT @@GLOBAL.innodb_default_row_format', 'SET GLOBAL innodb_default_row_format=2', 'SELECT @@GLOBAL.innodb_default_row_format', 'SET GLOBAL innodb_default_row_format=3', 'SELECT @@GLOBAL.innodb_default_row_format', 'SET GLOBAL innodb_default_row_format=-1', 'SELECT @@GLOBAL.innodb_default_row_format', 'SET GLOBAL innodb_default_row_format=\'compact\'', 'SELECT @@GLOBAL.innodb_default_row_format', 'SET GLOBAL innodb_default_row_format=\'barracuda\'', 'SELECT @@GLOBAL.innodb_default_row_format', 'SET GLOBAL innodb_default_row_format=1.0', 'SELECT @@GLOBAL.innodb_default_row_format']];
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach ($campaigns as $index => $statements) {
                yield $release . '/' . $index => [$release, $statements];
            }
        }
    }

    /**
     * @param list<string> $statements
     */
    #[DataProvider('providerSql')]
    public function testTableFormatsFollowCreationSettingsAndAlterationHistory(string $release, array $statements): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $server = Server::start($release);
            try {
                $memory = new mysqli($server->host, 'root', '', port: $server->port);
                $expected = array_map(static fn (string $sql): string => serialize((new Native())->observe($native, $sql)), $statements);
                $actual = array_map(static fn (string $sql): string => serialize((new Native())->observe($memory, $sql)), $statements);
                self::assertSame($expected, $actual, $release);
            } finally {
                $native->close();
                $server->stop();
            }
        } finally {
            $container->stop();
        }
    }

    /**
     * @return iterable<string, array{string, list<\SqlSemantics\Platform\MySql\Statement\Table\CreateTable>}>
     */
    public static function providerStructures(): iterable
    {
        $choices = new \Fuzz\Structure\Choices('row-format-state-20261017');
        $structures = [];
        foreach ([null, ...\SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\RowFormat::cases()] as $index => $format) {
            $column = new \SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition(new \SqlSemantics\Platform\MySql\Statement\Name\ColumnName(new \SqlSemantics\Statement\Identifier\Name('a')), new \SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn(new \SqlSemantics\Platform\MySql\Statement\Type\Integral(\SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind::Int)));
            $name = new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t' . $index . '_' . $choices->integer(0, 1000)), new \SqlSemantics\Statement\Identifier\Name('d'));
            $structures[] = new \SqlSemantics\Platform\MySql\Statement\Table\CreateTable($name, [$column], $format === null ? [] : [new \SqlSemantics\Platform\MySql\Statement\Table\Option\RowFormatOption($format)]);
        }
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $structures];
        }
    }

    /**
     * @param list<\SqlSemantics\Platform\MySql\Statement\Table\CreateTable> $structures
     */
    #[DataProvider('providerStructures')]
    public function testUnchangedDefinitionsResolveCurrentEngineSettingsWithoutSourceInput(string $release, array $structures): void
    {
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = new mysqli($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            $session = (new \MySqlMemory\Instance($release))->connect();
            array_map(static function (string $sql) use ($native, $session): void {
                $native->query($sql);
                $session->query($sql);
            }, ['CREATE DATABASE d', 'SET innodb_strict_mode=0']);
            $states = $release === '5.6.51' ? ["SET GLOBAL innodb_file_format='Antelope'", "SET GLOBAL innodb_file_format='Barracuda'", "SET GLOBAL innodb_file_format='Antelope'"] : ["SET GLOBAL innodb_default_row_format='compact'", "SET GLOBAL innodb_default_row_format='dynamic'", "SET GLOBAL innodb_default_row_format='redundant'"];
            $observations = array_map(static function (\SqlSemantics\Platform\MySql\Statement\Table\CreateTable $structure) use ($native, $session, $states): array {
                $sql = (new \Fuzz\Structure\Reference())->sql($structure, $session->semantics()->profile());
                return array_map(static function (string $change) use ($native, $session, $structure, $sql): array {
                    $native->query($change);
                    $session->query($change);
                    $session->text = 'unrelated invalid input';
                    $pair = [(new Native())->observe($native, $sql), (new \Fuzz\Structure\Audit\Memory())->observe($session, $structure)];
                    $name = $structure->name->name->value;
                    $row = $native->query("SELECT ROW_FORMAT FROM information_schema.tables WHERE table_schema='d' AND table_name='" . $name . "'");
                    self::assertInstanceOf(mysqli_result::class, $row);
                    $definition = $session->instance->dictionary->table('d', $name)?->definition;
                    self::assertNotNull($definition);
                    self::assertSame($row->fetch_row()[0] ?? null, ucfirst(strtolower($definition->rowFormat->value)), $change . ': ' . $sql);
                    self::assertSame('unrelated invalid input', $session->text);
                    $native->query('DROP TABLE d.' . $name);
                    $session->query('DROP TABLE d.' . $name);
                    return $pair;
                }, $states);
            }, $structures);
            $flat = array_merge(...$observations);
            self::assertSame(array_map(serialize(...), array_column($flat, 0)), array_map(serialize(...), array_column($flat, 1)));
            $native->close();
        } finally {
            $container->stop();
        }
    }

}
