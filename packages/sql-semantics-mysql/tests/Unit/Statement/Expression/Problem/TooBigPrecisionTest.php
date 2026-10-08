<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\TooBigPrecision;

#[CoversClass(TooBigPrecision::class)]
#[Small]
final class TooBigPrecisionTest extends TestCase
{
    public function testMessageNamesThePrecisionAndTheFunction(): void
    {
        self::assertSame("Too-big precision 7 specified for 'CAST'. Maximum is 6.", (new TooBigPrecision(7, 'CAST'))->message());
    }
}
