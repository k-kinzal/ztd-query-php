<?php

declare(strict_types=1);

namespace Tests\Integration;

use mysqli_result;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\MySqlContainer;
use ZtdQuery\Adapter\Mysqli\ZtdMysqli;

#[CoversNothing]
#[Large]
final class MysqliFullTextSearchTest extends TestCase
{
    public function testMatchAgainstReadsOnlyShadowRows(): void
    {
        [$databaseName, $rawMysqli] = MySqlContainer::createTestDatabase();

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
