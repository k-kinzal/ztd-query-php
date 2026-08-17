<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\MySqlContainer;
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;

#[CoversNothing]
#[Large]
final class MysqliLoadDataTest extends TestCase
{
    public function testLocalInfileLoadsOnlyTheShadowTable(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();
        $table = 'load_' . bin2hex(random_bytes(8));
        $rawMysqli->query(sprintf('CREATE TABLE `%s` (id INT PRIMARY KEY, name VARCHAR(100))', $table));
        $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli);

        try {
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
            self::assertInstanceOf(\mysqli_result::class, $rows);
            self::assertSame([
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ], $rows->fetch_all(MYSQLI_ASSOC));

            $physical = $rawMysqli->query(sprintf('SELECT COUNT(*) AS count FROM `%s`', $table));
            self::assertInstanceOf(\mysqli_result::class, $physical);
            self::assertSame([['count' => '0']], $physical->fetch_all(MYSQLI_ASSOC));
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
