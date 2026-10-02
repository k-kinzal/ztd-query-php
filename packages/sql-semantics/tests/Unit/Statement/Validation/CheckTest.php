<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Validation\Check;
use SqlSemantics\Statement\Validation\Failure\InvalidConstruction;
use SqlSemantics\Statement\Validation\Failure\InvariantViolation;

#[CoversClass(Check::class)]
#[Small]
final class CheckTest extends TestCase
{
    public function testInputRejectsAnInvalidNameWithAssertionsDisabledAsWell(): void
    {
        $this->expectException(InvalidConstruction::class);
        new Name('two tokens');
    }

    #[\PHPUnit\Framework\Attributes\TestWith([false])]
    public function testInvariantClassifiesAnInternalContradictionSeparately(bool $consistent): void
    {
        $this->expectException(InvariantViolation::class);
        $this->expectExceptionMessage('The actual output lost WHERE.');
        Check::invariant($consistent, 'The actual output lost WHERE.');
    }

    public function testInputAcceptsAValidClosedName(): void
    {
        self::assertSame('valid', (new Name('valid'))->value);
    }
}
