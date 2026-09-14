<?php

declare(strict_types=1);

namespace Tests\Integration;

use Containers\MySql80Container;
use Containers\MySql84Container;
use mysqli;
use mysqli_result;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;

#[\PHPUnit\Framework\Attributes\CoversClass(ZtdMysqli::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\ZtdMysqliStatement::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Driver\MysqliConnection::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Driver\MysqliResultStatement::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Driver\MysqliResultColumnExtractor::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\MysqliStatementBindingBridge::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\ZtdMysqliException::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Native\MysqliPropertyReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\MysqliResultProcessor::class)]
#[Large]
final class MysqliLoadDataTest extends TestCase
{
    public function testLocalInfileLoadsOnlyTheShadowTable(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $rawMysqli = $container->getData(mysqli::class);
            $table = 'load_' . bin2hex(random_bytes(8));
            $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT PRIMARY KEY, name VARCHAR(100))', $table));
            $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli);

            $stream = tmpfile();
            self::assertIsResource($stream);
            self::assertSame(14, fwrite($stream, "1\tAlice\n2\tBob\n"));
            $metadata = stream_get_meta_data($stream);
            $path = $metadata['uri'] ?? null;
            self::assertIsString($path);

            self::assertNotFalse($ztdMysqli->query(sprintf(
                "LOAD DATA LOCAL INFILE '%s' INTO TABLE `%s`",
                str_replace("'", "''", $path),
                $table,
            )));
            self::assertSame(2, $ztdMysqli->lastAffectedRows());

            $rows = $ztdMysqli->query(sprintf('SELECT id, name FROM `%s` ORDER BY id', $table));
            self::assertInstanceOf(mysqli_result::class, $rows);
            self::assertSame([
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ], $rows->fetch_all(MYSQLI_ASSOC));

            $physical = $rawMysqli->query(sprintf('SELECT COUNT(*) AS count FROM `%s`', $table));
            self::assertInstanceOf(mysqli_result::class, $physical);
            self::assertSame([['count' => '0']], $physical->fetch_all(MYSQLI_ASSOC));
        } finally {
            $container->stop();
        }
    }
}
