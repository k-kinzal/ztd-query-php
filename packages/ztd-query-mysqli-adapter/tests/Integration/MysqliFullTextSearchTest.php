<?php

declare(strict_types=1);

namespace Tests\Integration;

use mysqli;
use mysqli_result;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;

#[\PHPUnit\Framework\Attributes\CoversClass(ZtdMysqli::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\ZtdMysqliStatement::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\MysqliConnection::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\MysqliResultStatement::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\MysqliResultColumnExtractor::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\MysqliStatementBindingBridge::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\ZtdMysqliException::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Native\MysqliPropertyReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\MysqliResultProcessor::class)]
#[Large]
final class MysqliFullTextSearchTest extends TestCase
{
    public function testMatchAgainstReadsOnlyShadowRows(): void
    {
        $host = getenv('ZTD_TEST_MYSQL_HOST');
        $port = getenv('ZTD_TEST_MYSQL_PORT');
        self::assertIsString($host);
        self::assertIsString($port);
        $port = (int) $port;
        $rawMysqli = new mysqli($host, 'root', 'root', '', $port);
        $rawMysqli->set_charset('utf8mb4');
        $databaseName = 'ztd_' . bin2hex(random_bytes(8));
        $rawMysqli->query('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4');
        $rawMysqli->select_db($databaseName);

        try {
            $rawMysqli->query(
                'CREATE TABLE articles (id INT PRIMARY KEY, title VARCHAR(255), body TEXT, '
                . 'FULLTEXT KEY article_search (title, body)) ENGINE=InnoDB',
            );
            $ztdMysqli = ZtdMysqli::fromMysqli($rawMysqli);
            self::assertNotFalse($ztdMysqli->query(
                "INSERT INTO articles VALUES (1, 'Search guide', 'exact search terms'), "
                . "(2, 'Other', 'unrelated')",
            ));

            $result = $ztdMysqli->query(
                "SELECT id FROM articles WHERE MATCH(title, body) AGAINST ('search terms')",
            );
            self::assertInstanceOf(mysqli_result::class, $result);
            self::assertSame([['id' => 1]], $result->fetch_all(MYSQLI_ASSOC));

            $physical = $rawMysqli->query('SELECT COUNT(*) AS aggregate FROM articles');
            self::assertInstanceOf(mysqli_result::class, $physical);
            self::assertSame([['aggregate' => '0']], $physical->fetch_all(MYSQLI_ASSOC));
        } finally {
            $rawMysqli->query(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
