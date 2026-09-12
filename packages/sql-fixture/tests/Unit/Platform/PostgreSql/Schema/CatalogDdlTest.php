<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\PostgreSql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\PostgreSql\Schema\CatalogDdl as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogColumn::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\PostgreSql\Schema\CatalogQuery::class)]
final class CatalogDdlTest extends TestCase
{
    public function testReconstructCreateTableIncludesDatabaseConstraints(): void
    {
        $pdo = \Tests\Fixture\Database::postgres();
        $pdo->exec('CREATE TEMPORARY TABLE users (id INTEGER PRIMARY KEY, name VARCHAR(30) NOT NULL DEFAULT \'ready\')');
        $statement = $pdo->query('SELECT pg_my_temp_schema()::regnamespace::text');
        self::assertNotFalse($statement);
        $namespace = $statement->fetchColumn();
        self::assertIsString($namespace);
        $sql = (new Subject())->reconstructCreateTable($pdo, $namespace . '.users');
        self::assertNotNull($sql);
        self::assertStringContainsString('"name" VARCHAR(30) NOT NULL', $sql);
        self::assertStringContainsString('PRIMARY KEY ("id")', $sql);
    }
}
