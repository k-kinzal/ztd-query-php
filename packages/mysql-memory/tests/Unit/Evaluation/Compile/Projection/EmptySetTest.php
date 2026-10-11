<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile\Projection;

use MySqlMemory\Evaluation\Compile\Projection\EmptySet;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmptySet::class)]
#[Small]
final class EmptySetTest extends TestCase
{
    public function testReadablePreservesTheVersionedAvailabilityOfNonnullableFields(): void
    {
        $modern = (new Instance('9.1.0'))->connect()->query('SELECT (SELECT 1,NULL UNION ALL SELECT 2,NULL LIMIT 0) <=> (0,NULL)')[0];
        $previous = (new Instance('8.4.7'))->connect()->query('SELECT (SELECT 1,NULL UNION ALL SELECT 2,NULL LIMIT 0) <=> (0,NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $modern);
        self::assertInstanceOf(ResultSet::class, $previous);
        self::assertSame([['0']], $modern->rows);
        self::assertSame([['1']], $previous->rows);
    }

    public function testDirectKeepsLocallyBoundedOperandsOnTheirBufferedPath(): void
    {
        $result = (new Instance('9.1.0'))->connect()->query('SELECT ((SELECT 1,NULL LIMIT 1) UNION ALL (SELECT 2,NULL LIMIT 1) LIMIT 0) <=> (0,NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }

    public function testSelectedSeparatesResolvedTruthFromFalseAndRuntimeConditions(): void
    {
        $result = (new Instance('9.1.0'))->connect()->query('SELECT (SELECT 1,NULL FROM DUAL WHERE 1=1 UNION ALL SELECT 2,NULL FROM DUAL WHERE 1=1 LIMIT 0) <=> (0,NULL), (SELECT 1,NULL FROM DUAL WHERE 1=0 UNION ALL SELECT 2,NULL FROM DUAL WHERE 1=0 LIMIT 0) <=> (0,NULL), (SELECT 1,NULL FROM DUAL WHERE RAND()<2 UNION ALL SELECT 2,NULL FROM DUAL WHERE RAND()<2 LIMIT 0) <=> (0,NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0','1','1']], $result->rows);
    }
}
