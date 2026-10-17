<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Storage\InsertNulls;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(InsertNulls::class)]
#[Small]
final class InsertNullsTest extends TestCase
{
    public function testValueDistinguishesIgnoredQueryValuesFromLiteralRows(): void
    {
        $session = (new Instance('5.7.44', [], ['d']))->connect('root', 'localhost', 'd');
        $session->query('CREATE TABLE t(a POINT NOT NULL); INSERT IGNORE INTO t VALUES(NULL); INSERT IGNORE INTO t SELECT NULL');
        $result = $session->query('SELECT HEX(a) FROM t')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['']], $result->rows);
    }
}
