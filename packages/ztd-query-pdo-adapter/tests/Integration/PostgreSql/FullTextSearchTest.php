<?php

declare(strict_types=1);

namespace Tests\Integration\PostgreSql;

use Container\Endpoint;
use Container\PostgreSql16Container;
use PDO;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\ZtdPdo;

/**
 * @requires extension pdo_pgsql
 * @group integration
 * @group postgres
 */
#[CoversNothing]
#[Large]
final class FullTextSearchTest extends TestCase
{
    public function testTextSearchFunctionsPreserveTsvectorShadowType(): void
    {
        $endpoint = \Testcontainers\Testcontainers::run(PostgreSql16Container::class)->getData(Endpoint::class);
        /** @var PDO $rawPdo */
        $rawPdo = new PDO(
            $endpoint->dsn(),
            $endpoint->username,
            $endpoint->password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );

        $schemaName = 'ztd_' . bin2hex(random_bytes(8));
        $rawPdo->exec(sprintf('CREATE SCHEMA "%s"', $schemaName));
        $rawPdo->exec(sprintf('SET search_path TO "%s"', $schemaName));


        try {
            $rawPdo->exec(
                'CREATE TABLE articles (id INTEGER PRIMARY KEY, title TEXT, body TEXT, '
                . 'search_document TSVECTOR GENERATED ALWAYS AS '
                . "(to_tsvector('english', coalesce(title, '') || ' ' || coalesce(body, ''))) STORED)",
            );
            $ztdPdo = ZtdPdo::fromPdo($rawPdo);
            self::assertSame(3, $ztdPdo->exec(
                'INSERT INTO articles (id, title, body) VALUES '
                . "(1, 'Search guide', 'exact search terms'), "
                . "(2, 'Body match', 'needle in body'), "
                . "(3, 'Other', 'unrelated')",
            ));

            $typed = $ztdPdo->query(
                'SELECT id, pg_typeof(search_document)::text AS type FROM articles '
                . "WHERE search_document @@ plainto_tsquery('english', 'search terms')",
            );
            self::assertNotFalse($typed);
            self::assertSame([['id' => 1, 'type' => 'tsvector']], $typed->fetchAll());

            $prepared = $ztdPdo->prepare(
                "SELECT id FROM articles WHERE to_tsvector('english', body) @@ plainto_tsquery('english', $1)",
            );
            self::assertNotFalse($prepared);
            self::assertTrue($prepared->execute(['needle']));
            self::assertSame([2], $prepared->fetchAll(PDO::FETCH_COLUMN));

            $physical = $rawPdo->query('SELECT COUNT(*) FROM articles');
            self::assertNotFalse($physical);
            self::assertSame(0, (int) $physical->fetchColumn());
        } finally {
            $rawPdo->exec(sprintf('DROP SCHEMA IF EXISTS "%s" CASCADE', $schemaName));
        }
    }
}
