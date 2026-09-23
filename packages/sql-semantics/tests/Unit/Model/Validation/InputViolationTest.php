<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Validation\InputViolation;

#[CoversClass(InputViolation::class)]
final class InputViolationTest extends TestCase
{
    #[DataProvider('providerViolations')]
    public function testMessageDescribesEveryClassifiedOperandFailure(InputViolation $violation): void
    {
        self::assertNotEmpty($violation->message());
        self::assertStringEndsWith('.', $violation->message());
        self::assertNotSame($violation->value, $violation->message());
    }

    /**
     * @return iterable<array{InputViolation}>
     */
    public static function providerViolations(): iterable
    {
        return array_map(static fn (InputViolation $violation): array => [$violation], InputViolation::cases());
    }
}
