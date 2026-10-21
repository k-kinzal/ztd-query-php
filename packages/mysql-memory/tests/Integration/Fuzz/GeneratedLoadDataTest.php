<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Metadata;
use Fuzz\Structure\Reference;
use Fuzz\Target\DisposableMySql;
use JsonException;
use mysqli;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement as M;
use Testcontainers\Testcontainers;

#[CoversNothing]
#[Large]
final class GeneratedLoadDataTest extends TestCase
{
    /**
     * @return iterable<string, array{string, M\Dml\Load\LoadTable}>
     * @throws JsonException When the semantic inventory cannot be decoded
     * @throws ReflectionException When constructor metadata cannot be read
     */
    public static function providerStatements(): iterable
    {
        $catalogue = new Catalogue();
        $roots = $catalogue->roots();
        $choices = new Choices('row-roles-20261017:56');
        $choices->one($roots);
        $node = (new Construction($choices, $catalogue, new Metadata()))->object($roots[56], 6);
        Check::invariant($node instanceof M\Dml\Load\LoadTable && $node->input->file->value === 'A1B2C', 'The recorded whole-model root retains its client file identity.');
        foreach (['5.6.51', '5.7.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $node];
        }
    }

    #[DataProvider('providerStatements')]
    public function testTheSameLoadConsumesClientDataAfterItsPartitionIsProvided(string $release, M\Dml\Load\LoadTable $node): void
    {
        $directory = sys_get_temp_dir() . '/memory-load-' . bin2hex(random_bytes(8));
        mkdir($directory);
        file_put_contents($directory . '/A1B2C', '');
        $previous = getcwd();
        self::assertIsString($previous);
        $container = Testcontainers::run(new DisposableMySql($release));
        try {
            chdir($directory);
            $endpoint = $container->getData(Endpoint::class);
            $native = mysqli_init();
            self::assertInstanceOf(mysqli::class, $native);
            $native->options(MYSQLI_OPT_LOAD_DATA_LOCAL_DIR, $directory);
            $native->real_connect($endpoint->host, $endpoint->username, $endpoint->password, port: $endpoint->port);
            try {
                $native->query('SET GLOBAL local_infile=1');
                $native->query('CREATE DATABASE d');
                $native->select_db('d');
                $session = (new Instance($release, ['local_infile' => 'ON'], ['d']))->connect(database: 'd');
                $contents = '';
                $session->running->readFile = static function (string $file) use (&$contents): string {
                    self::assertSame('A1B2C', $file);
                    return $contents;
                };
                $sql = (new Reference())->sql($node, $session->semantics()->profile());
                array_map(static function (array $fixture) use ($native, $session, $node, $sql, &$contents): void {
                    [$setup, $contents, $code, $affected, $rows] = $fixture;
                    array_map(static function (string $step) use ($native, $session): void {
                        $statement = $session->analyze($step)->statement;
                        self::assertInstanceOf(M\Statement::class, $statement);
                        self::assertSame((new Native())->observe($native, $step), (new Memory())->observe($session, $statement));
                    }, $setup);
                    file_put_contents('A1B2C', $contents);
                    $expected = (new Native())->observe($native, $sql);
                    $session->text = 'opaque request label';
                    self::assertSame($expected, (new Memory())->observe($session, $node));
                    self::assertSame($code, $expected['error'][0] ?? null);
                    self::assertSame($affected, $expected['results'][0]['affected'] ?? null);
                    $query = 'SELECT a FROM `1.5` ORDER BY a';
                    $read = (new Native())->observe($native, $query);
                    $statement = $session->analyze($query)->statement;
                    self::assertInstanceOf(M\Statement::class, $statement);
                    self::assertSame($read, (new Memory())->observe($session, $statement));
                    self::assertSame($rows, $read['results'][0]['rows'] ?? null);
                }, [
                    [[], "1\n2\n", 1146, null, null],
                    [['CREATE TABLE `1.5`(a INT)'], "1\n2\n", 1747, null, []],
                    [['DROP TABLE `1.5`', 'CREATE TABLE `1.5`(a INT) PARTITION BY RANGE(a) (PARTITION `a b` VALUES LESS THAN MAXVALUE)'], "1\n2\n", null, 2, [['1'], ['2']]],
                    [['TRUNCATE TABLE `1.5`'], "3\n4\n", null, 2, [['3'], ['4']]],
                    [['DROP TABLE `1.5`'], "5\n6\n", 1146, null, null],
                ]);
                self::assertSame($sql, (new Reference())->sql($node, $session->semantics()->profile()));
            } finally {
                $native->close();
            }
        } finally {
            $container->stop();
            chdir($previous);
            unlink($directory . '/A1B2C');
            rmdir($directory);
        }
    }
}
