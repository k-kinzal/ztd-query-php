<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Call\Preparation;

use Deriver\Deriver\Evaluator\Execution\Call\Preparation\Target;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Execution\Call\Preparation\Target
 */
#[CoversClass(Target::class)]
#[Small]
final class TargetTest extends TestCase
{
    public function testDefaultsDistinguishUnresolvedSignaturesFromCertainErrors(): void
    {
        $unknown = new Target();
        $error = new Target(error: 'Error');
        self::assertNull($unknown->signature);
        self::assertSame('', $unknown->error);
        self::assertNull($error->signature);
        self::assertSame('Error', $error->error);
    }
}
