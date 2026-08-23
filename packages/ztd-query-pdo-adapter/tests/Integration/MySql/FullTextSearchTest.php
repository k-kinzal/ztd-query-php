<?php

declare(strict_types=1);

namespace Tests\Integration\MySql;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\MySqlContainer;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_mysql
 * @group integration
 * @group mysql
 */
#[CoversNothing]
#[Large]
final class FullTextSearchTest extends TestCase
{
    public function testMatchAgainstReadsOnlyShadowRows(): void
    {
        [$databaseName, $rawPdo] = MySqlContainer::createTestDatabase();

        try {
            $rawPdo->exec(
                'CREATE TABLE articles (id INT PRIMARY KEY, title VARCHAR(255), body TEXT, '
                . 'FULLTEXT KEY article_search (title, body)) ENGINE=InnoDB',
            );
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            self::assertSame(3, $ztdPdo->exec(
                "INSERT INTO articles VALUES "
                . "(1, 'Search guide', 'exact search terms'), "
                . "(2, 'Body match', 'needle in body'), "
                . "(3, 'Other', 'unrelated')",
            ));

            $literal = $ztdPdo->query(
                "SELECT id, MATCH(title, body) AGAINST ('search terms') AS score "
                . "FROM articles WHERE MATCH(title, body) AGAINST ('search terms') ORDER BY score DESC",
            );
            self::assertNotFalse($literal);
            self::assertSame([['id' => 1, 'score' => '1.0']], $literal->fetchAll());

            $prepared = $ztdPdo->prepare(
                'SELECT id FROM articles WHERE MATCH(title, body) AGAINST (?)',
            );
            self::assertNotFalse($prepared);
            self::assertTrue($prepared->execute(['needle']));
            self::assertSame([2], $prepared->fetchAll(PDO::FETCH_COLUMN));

            $physical = $rawPdo->query('SELECT COUNT(*) FROM articles');
            self::assertNotFalse($physical);
            self::assertSame(0, (int) $physical->fetchColumn());
        } finally {
            $rawPdo->exec(sprintf('DROP DATABASE IF EXISTS `%s`', $databaseName));
        }
    }
}
