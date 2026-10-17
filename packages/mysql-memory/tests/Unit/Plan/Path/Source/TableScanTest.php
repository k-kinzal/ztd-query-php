<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Path\Source;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Path\Source\TableScan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(TableScan::class)]
#[Small]
final class TableScanTest extends TestCase
{
    public function testWidthCountsEveryColumnOfTheTableIncludingInvisibleOnes(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT INVISIBLE, c VARCHAR(3))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame(3, (new TableScan($table))->width());
    }
}
