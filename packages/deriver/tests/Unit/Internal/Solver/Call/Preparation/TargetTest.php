<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Solver\Call\Preparation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Solver\Call\Preparation\Target
 */
#[CoversClass(\Deriver\Internal\Solver\Call\Preparation\Target::class)]
#[Small]
final class TargetTest extends TestCase
{
    public function testDefaultsDistinguishUnresolvedSignaturesFromCertainErrors(): void
    {
        $unknown = new \Deriver\Internal\Solver\Call\Preparation\Target();
        $error = new \Deriver\Internal\Solver\Call\Preparation\Target(error: 'Error');
        self::assertNull($unknown->signature);
        self::assertSame('', $unknown->error);
        self::assertNull($error->signature);
        self::assertSame('Error', $error->error);
    }
}
