<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Container\Endpoint;
use Fuzz\Structure\Audit\Memory;
use Fuzz\Structure\Audit\Native;
use Fuzz\Target\DisposableMySql;
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
final class LoadClientRecordsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<array{string, string, string}>}>
     */
    public static function providerRecords(): iterable
    {
        $cases = [
            ['default', "1\talpha\n2\t\\N\n", ''],
            ['comma', "1,a\n2,b\n", "FIELDS TERMINATED BY ','"],
            ['enclosed', "1,\"a,b\"\n2,\"a\nb\"\n", "FIELDS TERMINATED BY ',' ENCLOSED BY '\"'"],
            ['nulls', "1,NULL\n2,\"NULL\"\n3,\\N\n4,\"\\N\"\n", "FIELDS TERMINATED BY ',' ENCLOSED BY '\"'"],
            ['missing and extra', "1\n2,a,b\n3,\n\n", "FIELDS TERMINATED BY ','"],
            ['prefix', "header\nxx1,a\nignore\nxx2,b\n", "FIELDS TERMINATED BY ',' LINES STARTING BY 'xx' IGNORE 1 LINES"],
            ['variables', "1,alpha\n2,\\N\n", "FIELDS TERMINATED BY ',' (a,@b) SET b=@b"],
            ['prior numeric variable with UTF-8 input', "1,alpha\n2,beta\n", "CHARACTER SET utf8mb4 FIELDS TERMINATED BY ',' (a,@b) SET b=CHARSET(@b)"],
            ['prior numeric variable with Latin-1 input', "1,alpha\n2,beta\n", "CHARACTER SET latin1 FIELDS TERMINATED BY ',' (a,@b) SET b=CHARSET(@b)"],
            ['fixed', "001abc\n002def\n", "FIELDS TERMINATED BY '' ENCLOSED BY ''"],
            ['fixed complete', "00000000001alpha\n00000000002beta\n", "FIELDS TERMINATED BY '' ENCLOSED BY ''"],
        ];
        foreach (['5.6.51', '5.7.44', '8.4.7', '9.1.0'] as $release) {
            yield $release => [$release, $cases];
        }
    }

    /**
     * @param list<array{string, string, string}> $cases File label, bytes and semantic layout
     */
    #[DataProvider('providerRecords')]
    public function testClientFilesPreserveRowsWarningsAndMetadata(string $release, array $cases): void
    {
        $directory = sys_get_temp_dir() . '/load-records-' . bin2hex(random_bytes(8));
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
                    }, ['DROP TABLE IF EXISTS t', 'CREATE TABLE t(a INT,b VARCHAR(30))', 'SET @b=7', "LOAD DATA LOCAL INFILE '$file' INTO TABLE t $options", 'SELECT * FROM t ORDER BY a']);
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
}
