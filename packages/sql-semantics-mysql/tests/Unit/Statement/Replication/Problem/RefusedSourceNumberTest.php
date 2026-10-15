<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\RefusedSourceNumber;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\SourceOptionKind;

#[CoversClass(RefusedSourceNumber::class)]
#[Small]
final class RefusedSourceNumberTest extends TestCase
{
    public function testMessageRetainsAnInvalidOperandAndItsPosition(): void
    {
        $number = new Numeral('2');
        $problem = new RefusedSourceNumber(SourceOptionKind::GtidOnly, $number, 39);

        self::assertSame('GTID_ONLY requires a value in its numeric domain.', $problem->message());
        self::assertSame($number, $problem->number);
        self::assertSame(39, $problem->offset);
    }
}
