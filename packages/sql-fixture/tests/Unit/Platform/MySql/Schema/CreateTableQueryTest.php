<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\MySql\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Platform\MySql\Schema\CreateTableQuery as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\Medium]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Platform\MySql\Schema\IdentifierQuoter::class)]
final class CreateTableQueryTest extends TestCase
{
    public function testFetchCreateTableSqlReadsLiveTemporaryTable(): void
    {
        $pdo = \Tests\Fixture\Database::mysql();
        $pdo->exec('CREATE TEMPORARY TABLE users (id INT PRIMARY KEY, name VARCHAR(30) NOT NULL)');
        $sql = (new Subject())->fetchCreateTableSql($pdo, 'users');
        self::assertStringContainsString('`name` varchar(30) NOT NULL', $sql);
        self::assertStringContainsString('PRIMARY KEY (`id`)', $sql);
    }
}
