<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Definition\Partition;

use MySqlMemory\Command\Definition\Partition\QueryRows;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryRows::class)]
#[Small]
final class QueryRowsTest extends TestCase
{
    public function testAcceptKeepsTheLegacyCreationDiagnosticsAndSilentIgnoredRows(): void
    {
        $session = (new Instance('5.6.51', [], ['d']))->connect();
        $refused = $session->run('CREATE TABLE d.t PARTITION BY RANGE(a) (PARTITION p VALUES LESS THAN(1)) SELECT 2 AS a')[0];

        self::assertInstanceOf(SqlError::class, $refused);
        self::assertSame(1526, $refused->getCode());
        self::assertSame([['Error', 1526, 'Table has no partition for value 2'], ['Error', 1567, 'Incorrect partition name']], $session->diagnostics->conditions);
        self::assertNull($session->instance->dictionary->table('d', 't'));

        $ignored = $session->query('CREATE TABLE d.t PARTITION BY RANGE(a) (PARTITION p VALUES LESS THAN(1)) IGNORE SELECT 2 AS a')[0];

        self::assertInstanceOf(Completion::class, $ignored);
        self::assertSame('Records: 1  Duplicates: 1  Warnings: 0', $ignored->info);
        self::assertSame([], $session->diagnostics->conditions);
    }
}
