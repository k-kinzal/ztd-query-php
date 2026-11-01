<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Server\StorageSizes;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\FractionalNumber;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\StorageRule;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(StorageSizes::class)]
#[Small]
final class StorageSizesTest extends TestCase
{
    public function testProblemChecksTheIntegerAndUnitCoefficientBoundaries(): void
    {
        $sizes = new StorageSizes();

        self::assertNull($sizes->problem(new Numeral('18446744073709551615'), null));
        self::assertNull($sizes->problem(null, new Name('2147483647G')));
        self::assertNull($sizes->problem(null, new Name('000K')));
        self::assertInstanceOf(FractionalNumber::class, $sizes->problem(new Numeral('18446744073709551616'), null));
        self::assertInstanceOf(FractionalNumber::class, $sizes->problem(new Numeral('1.5'), null));
        self::assertEquals(new StorageProblem(StorageRule::WrongSize, 'SIZE'), $sizes->problem(null, new Name('3T')));
        self::assertEquals(new StorageProblem(StorageRule::SizeOverflow, 'SIZE'), $sizes->problem(null, new Name('2147483648K')));
    }
}
