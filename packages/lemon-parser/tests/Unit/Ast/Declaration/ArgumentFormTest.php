<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use LemonParser\Ast\Declaration\ArgumentForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArgumentForm::class)]
#[Small]
final class ArgumentFormTest extends TestCase
{
    public function testCases(): void
    {
        self::assertSame(['code', 'string', 'word'], array_map(static fn (ArgumentForm $case): string => $case->value, ArgumentForm::cases()));
    }
}
