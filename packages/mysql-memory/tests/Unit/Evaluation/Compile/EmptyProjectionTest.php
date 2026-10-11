<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Compile;

use MySqlMemory\Evaluation\Compile\EmptyProjection;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmptyProjection::class)]
#[Small]
final class EmptyProjectionTest extends TestCase
{
    public function testOfKeepsNullProducingExpressionsReadableWithoutAnInputRow(): void
    {
        $result = (new Instance())->connect()->query('SELECT (SELECT SQRT(-1),CONCAT(NULL) FROM DUAL WHERE 0) <=> (NULL,NULL), (SELECT 1,NULL LIMIT 0) <=> (2,NULL)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '1']], $result->rows);
    }

    public function testOfDoesNotTreatWindowOutputAsAnIndependentExpression(): void
    {
        $result = (new Instance())->connect()->query('SELECT (SELECT ROW_NUMBER() OVER (),1) <=> (1,1)')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1']], $result->rows);
    }
}
