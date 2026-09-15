<?php

declare(strict_types=1);

namespace Tests\Integration;

use mysqli;
use mysqli_result;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Testcontainers\Testcontainers;
use Tests\Container\MySql80Container;
use Tests\Container\MySql84Container;
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;

#[\PHPUnit\Framework\Attributes\CoversClass(ZtdMysqli::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Session\ConnectionExecution::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\ZtdMysqliStatement::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Session\StatementExecution::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Driver\MysqliConnection::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Driver\MysqliResultStatement::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Driver\MysqliResultColumnExtractor::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Native\MysqliStatementBindingBridge::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\ZtdMysqliException::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Native\MysqliPropertyReader::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\ZtdQuery\Adapter\Mysqli\Session\MysqliResultProcessor::class)]
#[Large]
final class MysqliFullTextSearchTest extends TestCase
{
    public function testMatchAgainstReadsOnlyShadowRows(): void
    {
        $container = Testcontainers::run(getenv('MYSQL_VERSION') === '8.4.7' ? MySql84Container::class : MySql80Container::class);
        try {
            $rawMysqli = $container->getData(mysqli::class);

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
            $container->stop();
        }
    }
}
