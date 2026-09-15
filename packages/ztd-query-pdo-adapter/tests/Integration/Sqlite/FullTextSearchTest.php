<?php

declare(strict_types=1);

namespace Tests\Integration\Sqlite;

use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_sqlite
 * @group integration
 * @group sqlite
 */
#[CoversNothing]
#[Large]
final class FullTextSearchTest extends TestCase
{
    public function testFts5SearchReadsOnlyShadowRows(): void
    {
        $rawPdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $rawPdo->exec('CREATE VIRTUAL TABLE fts_articles USING fts5(title, body)');
        $ztdPdo = ZtdPdo::fromPdo($rawPdo);

        $empty = $ztdPdo->query("SELECT title FROM fts_articles WHERE fts_articles MATCH 'search'");
        self::assertNotFalse($empty);
        self::assertSame([], $empty->fetchAll());

        self::assertSame(3, $ztdPdo->exec(
            'INSERT INTO fts_articles (title, body) VALUES '
            . "('Search guide', 'exact search terms'), "
            . "('Body match', 'needle in body'), "
            . "('Other', 'unrelated')",
        ));

        $tableMatch = $ztdPdo->query(
            "SELECT title FROM fts_articles WHERE fts_articles MATCH 'search terms'",
        );
        self::assertNotFalse($tableMatch);
        self::assertSame([['title' => 'Search guide']], $tableMatch->fetchAll());

        $columnMatch = $ztdPdo->query(
            "SELECT title FROM fts_articles WHERE body MATCH 'needle'",
        );
        self::assertNotFalse($columnMatch);
        self::assertSame([['title' => 'Body match']], $columnMatch->fetchAll());

        $prepared = $ztdPdo->prepare(
            'SELECT title FROM fts_articles WHERE fts_articles MATCH ?',
        );
        self::assertNotFalse($prepared);
        self::assertTrue($prepared->execute(['unrelated']));
        self::assertSame([['title' => 'Other']], $prepared->fetchAll());

        $equals = $ztdPdo->query(
            "SELECT title FROM fts_articles WHERE fts_articles = 'exact search'",
        );
        self::assertNotFalse($equals);
        self::assertSame([['title' => 'Search guide']], $equals->fetchAll());

        $physical = $rawPdo->query('SELECT COUNT(*) FROM fts_articles');
        self::assertNotFalse($physical);
        self::assertSame(0, (int) $physical->fetchColumn());
    }
}
