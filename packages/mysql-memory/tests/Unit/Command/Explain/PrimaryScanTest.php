<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Explain;

use MySqlMemory\Command\Explain\PrimaryScan;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrimaryScan::class)]
#[Small]
final class PrimaryScanTest extends TestCase
{
    public function testOfScansAnIntegerPrimaryKeyAndMarksKeyChanges(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (a BIGINT, b SMALLINT, c INT, PRIMARY KEY(a,b)); INSERT INTO t VALUES (1,2,3)');
        $result = $session->query('EXPLAIN UPDATE t SET c=4')[0];
        $changed = $session->query('EXPLAIN UPDATE t SET b=3')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $changed);
        self::assertSame(['index', null, 'PRIMARY', '10', null], array_slice($result->rows[0], 4, 5));
        self::assertNull($result->rows[0][11]);
        self::assertSame('Using temporary', $changed->rows[0][11]);
    }
}
