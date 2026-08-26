<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFixture\Platform\MySql\MySqlCatalog;
use SqlFixture\Platform\MySql\MySqlSchemaFetcher;
use SqlFixture\Platform\MySql\MySqlSchemaParser;

#[CoversClass(MySqlSchemaFetcher::class)]
#[UsesClass(MySqlCatalog::class)]
#[UsesClass(MySqlSchemaParser::class)]
final class MySqlSchemaFetcherTest extends TestCase
{
    public function testFetchSchemaReportsAConnectionThatWillNotAnswerAboutTheTable(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to get CREATE TABLE for: users');

        (new MySqlSchemaFetcher())->fetchSchema($pdo, 'users');
    }
}
