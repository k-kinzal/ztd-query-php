<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
use JsonException;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement as M;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class LoadXmlRecordsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{string, string, string}>}>
     * @throws JsonException When the saved XML inputs are invalid
     */
    public static function providerRecords(): iterable
    {
        /** @var list<array{string, string, string}> $cases */
        $cases = json_decode((string) file_get_contents(__DIR__ . '/fixtures/load-xml-records.json'), true, 512, JSON_THROW_ON_ERROR);
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $cases];
        }
    }

    /**
     * @param list<array{string, string, string}> $cases File label, bytes and semantic layout
     */
    #[DataProvider('providerRecords')]
    public function testXmlFilesPreserveRowsVariablesWarningsAndMetadata(string $release, array $cases): void
    {
        $directory = sys_get_temp_dir() . '/load-xml-records-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $file = $directory . '/input';
        file_put_contents($file, '');
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = mysqli_init();
            self::assertInstanceOf(mysqli::class, $native);
            $native->options(MYSQLI_OPT_LOAD_DATA_LOCAL_DIR, $directory);
            $native->real_connect($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance($release))->connect();
                array_map(static function (string $sql) use ($session, $native): void {
                    $statement = $session->analyze($sql)->statement;
                    self::assertInstanceOf(M\Statement::class, $statement);
                    self::assertSame((new Native())->observe($native, $sql), (new Memory())->observe($session, $statement));
                }, ['SET GLOBAL local_infile=1', 'CREATE DATABASE d', 'USE d']);
                array_map(static function (array $case) use ($session, $native, $file): void {
                    [$label, $contents, $options] = $case;
                    file_put_contents($file, $contents);
                    $session->running->readFile = static fn (string $path): string => $contents;
                    array_map(static function (string $sql) use ($session, $native, $label): void {
                        $statement = $session->analyze($sql)->statement;
                        self::assertInstanceOf(M\Statement::class, $statement);
                        $expected = (new Native())->observe($native, $sql);
                        $session->text = 'opaque LOAD request';
                        self::assertSame($expected, (new Memory())->observe($session, $statement), $label . ': ' . $sql);
                    }, ['DROP TABLE IF EXISTS t', 'CREATE TABLE t(a INT DEFAULT 9,b VARCHAR(100) DEFAULT \'default\')', 'SET @b=7', "LOAD XML LOCAL INFILE '$file' INTO TABLE t $options", 'SELECT a,b,HEX(b) FROM t', 'SELECT @b,HEX(@b),CHARSET(@b)']);
                }, $cases);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
            unlink($file);
            rmdir($directory);
        }
    }
    public function testXmlInputSurvivesPermissionAndCatalogTransitions(): void
    {
        $directory = sys_get_temp_dir() . '/load-xml-history-' . bin2hex(random_bytes(8));
        mkdir($directory);
        $file = $directory . '/input';
        $contents = '<item a="9" b="ignored"/><item a="1" b="alpha"/><item a="2"><b>beta</b></item>';
        file_put_contents($file, $contents);
        $container = Testcontainers::run(new DisposableMySql('9.1.0'));
        try {
            $endpoint = $container->getData(Endpoint::class);
            $native = mysqli_init();
            self::assertInstanceOf(mysqli::class, $native);
            $native->options(MYSQLI_OPT_LOAD_DATA_LOCAL_DIR, $directory);
            $native->real_connect($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $session = (new Instance('9.1.0'))->connect();
                $session->running->readFile = static fn (string $path): string => $contents;
                $node = new M\Dml\Load\LoadTable(M\Dml\Load\LoadFormat::Xml, null, new M\Dml\Load\LoadInput(true, M\Dml\Load\LoadSource::Infile, new M\Literal\Text($file)), M\Dml\DuplicateHandling::Replace, new M\Dml\WriteTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))), rowTag: new M\Literal\Text('<item>'), ignored: new M\Literal\Numeral('1'), columns: [new M\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('a')), new M\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('b'))], assignments: [new M\Dml\Assignment(new M\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('b')), new M\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('b')))]);
                $sql = "LOAD XML LOCAL INFILE '$file' REPLACE INTO TABLE t ROWS IDENTIFIED BY '<item>' IGNORE 1 ROWS (a,@b) SET b=@b";
                array_map(static function (string|M\Dml\Load\LoadTable $step) use ($native, $session, $sql): void {
                    $statement = is_string($step) ? $session->analyze($step)->statement : $step;
                    self::assertInstanceOf(M\Statement::class, $statement);
                    $expected = (new Native())->observe($native, is_string($step) ? $step : $sql);
                    $session->text = 'opaque constructed XML request';
                    self::assertSame($expected, (new Memory())->observe($session, $statement));
                }, ['SET GLOBAL local_infile=0', 'CREATE DATABASE d', 'USE d', $node, 'SET GLOBAL local_infile=1', $node, 'CREATE TABLE t(a INT PRIMARY KEY,b VARCHAR(30))', $node, 'SELECT * FROM t ORDER BY a', "UPDATE t SET b='changed' WHERE a=1", $node, 'SELECT * FROM t ORDER BY a', 'DROP TABLE t', $node, 'CREATE TABLE t(a INT PRIMARY KEY,b VARCHAR(40))', $node, 'SELECT * FROM t ORDER BY a']);
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
            unlink($file);
            rmdir($directory);
        }
    }

}
