<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\ViolationMessages;

#[CoversClass(ViolationMessages::class)]
final class ViolationMessagesTest extends TestCase
{
    #[DataProvider('providerViolations')]
    public function testOfDescribesEveryViolationAsASentence(InputViolation $violation): void
    {
        self::assertStringEndsWith('.', ViolationMessages::of($violation));
        self::assertNotSame($violation->value, ViolationMessages::of($violation));
    }

    /**
     * @return iterable<array{InputViolation}>
     */
    public static function providerViolations(): iterable
    {
        return array_map(static fn (InputViolation $violation): array => [$violation], InputViolation::cases());
    }
}
