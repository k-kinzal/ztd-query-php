<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Sqlite\Schema;

use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\Sqlite\Schema\CreateTableQuery as Subject;

#[CoversClass(Subject::class)]
final class CreateTableQueryTest extends TestCase
{
    public function testFetchCreateTableSqlReadsSqliteMaster(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(30) NOT NULL DEFAULT \'ready\')');
        $sql = (new Subject())->fetchCreateTableSql($pdo, 'users');
        self::assertNotNull($sql);
        self::assertStringContainsString('VARCHAR(30)', $sql);
    }
}
