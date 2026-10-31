<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Text;

use MySqlMemory\Evaluation\Function\Text\Lists;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Lists::class)]
#[Small]
final class ListsTest extends TestCase
{
    public function testRoutinesNamesTheFunctionsThatPickStrings(): void
    {
        self::assertSame(['ELT', 'MAKE_SET', 'EXPORT_SET'], array_map(static fn ($routine): string => $routine->name, (new Lists())->routines()));
    }

    public function testEltAnswersTheStringAtAPosition(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT ELT(2, 'a', 'bb'), ELT(0, 'a'), ELT(3, 'a', 'b'), ELT(1.5, 'a', 'b'), ELT(18446744073709551615, 'a'), HEX(ELT(1, x'FF', 'a'))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['bb', null, null, 'b', null, 'FF']], $result->rows);
        self::assertSame(8, $result->columns[0]->length);
    }

    public function testMakeSetJoinsTheStringsOfTheBitsSet(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT MAKE_SET(7, 'a', NULL, 'c'), MAKE_SET(3, '', 'b'), MAKE_SET(-1, 'a', 'b'), MAKE_SET(NULL, 'a'), MAKE_SET(0, 'a')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['a,c', ',b', 'a,b', null, '']], $result->rows);
    }

    public function testExportSetWritesOneStringForEachBit(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT EXPORT_SET(5, 'Y', 'N', ',', 4), EXPORT_SET(5, 'Y', 'N', '', 0), LENGTH(EXPORT_SET(5, 'Y', 'N', ',', 65)), EXPORT_SET(1.5, 'Y', 'N', ',', 2.5), EXPORT_SET(1, 'Y', 'N', NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['Y,N,Y,N', '', '127', 'N,Y,N', null]], $result->rows);
        self::assertSame(508, $result->columns[0]->length);
    }
}
