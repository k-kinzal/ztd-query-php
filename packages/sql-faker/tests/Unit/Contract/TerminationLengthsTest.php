<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\TerminationLengths;

#[CoversNothing]
final class TerminationLengthsTest extends TestCase
{
    public function testLengthOfReturnsStoredLengthOrOneFallback(): void
    {
        $lengths = new TerminationLengths(['stmt' => 3]);

        self::assertSame(3, $lengths->lengthOf('stmt'));
        self::assertSame(1, $lengths->lengthOf('missing'));
    }

    public function testConstructorRejectsInvalidMaps(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TerminationLengths(['' => 0]);
    }
}
