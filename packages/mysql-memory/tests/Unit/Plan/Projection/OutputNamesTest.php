<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Projection;

use MySqlMemory\Instance;
use MySqlMemory\Plan\Projection\OutputNames;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(OutputNames::class)]
#[Small]
final class OutputNamesTest extends TestCase
{
    public function testNameReadsALatin1LiteralInTheSystemCharacterSet(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT _latin1'é', (_latin1'  x€')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['Ã©', 'xâ‚¬'], [$result->columns[0]->name, $result->columns[1]->name]);
    }

    public function testViewedNamesAColumnOfAViewAsTheViewDoes(): void
    {
        $s = (new Instance())->connect();
        $s->query('CREATE DATABASE d');
        $s->query('USE d');
        $s->query('CREATE TABLE t (a INT)');
        $s->query('CREATE VIEW v AS SELECT a AS A1, a + 1 AS B FROM t');

        $read1 = $s->query('SELECT a1, b FROM v')[0];
        self::assertInstanceOf(ResultSet::class, $read1);
        $read2 = $s->query('SELECT a1, b FROM v')[0];
        self::assertInstanceOf(ResultSet::class, $read2);
        $read3 = $s->query('SELECT a1 AS a1 FROM v')[0];
        self::assertInstanceOf(ResultSet::class, $read3);
        $read4 = $s->query('SELECT table_name FROM information_schema.tables LIMIT 0')[0];
        self::assertInstanceOf(ResultSet::class, $read4);
        self::assertSame(['A1', 'B', 'a1', 'TABLE_NAME'], [$read1->columns[0]->name, $read2->columns[1]->name, $read3->columns[0]->name, $read4->columns[0]->name]);
    }
}
