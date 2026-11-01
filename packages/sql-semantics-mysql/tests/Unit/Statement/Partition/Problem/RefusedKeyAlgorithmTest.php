<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\RefusedKeyAlgorithm;

#[CoversClass(RefusedKeyAlgorithm::class)]
#[Small]
final class RefusedKeyAlgorithmTest extends TestCase
{
    public function testMessageRetainsOnlyTheAlgorithmAndItsDiagnosticPosition(): void
    {
        $number = new Numeral('', true);
        $problem = new RefusedKeyAlgorithm($number, 18);
        self::assertSame('A KEY partition algorithm is one or two.', $problem->message());
        self::assertSame($number, $problem->number);
        self::assertSame(18, $problem->offset);
    }
}
