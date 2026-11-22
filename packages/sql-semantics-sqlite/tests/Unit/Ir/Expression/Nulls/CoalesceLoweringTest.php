<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression\Nulls;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Semantics;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\CoalesceLowering
 */
final class CoalesceLoweringTest extends TestCase
{
    public function testLowerUnifiesBothCheckedBuiltinSpellings(): void
    {
        $semantics = new Semantics();
        $direct = $semantics->analyze('SELECT COALESCE(NULL,?07) AS total');
        $alias = $semantics->analyze('SELECT IFNULL(DISTINCT NULL,?07) AS total');
        self::assertInstanceOf(Select::class, $direct);
        self::assertInstanceOf(Select::class, $alias);

        self::assertInstanceOf(Coalesce::class, $alias->columns[0]->expression);
        self::assertEquals($direct, $alias);
        self::assertSame('SELECT COALESCE(NULL, ?07) AS "total"', $alias->toString());
    }
}
