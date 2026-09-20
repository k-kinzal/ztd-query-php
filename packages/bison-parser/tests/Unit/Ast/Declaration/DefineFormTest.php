<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use BisonParser\Ast\Declaration\DefineForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(DefineForm::class)]
#[Small]
final class DefineFormTest extends TestCase
{
    public function testCases(): void
    {
        self::assertSame(['keyword', 'string', 'code'], array_map(static fn (DefineForm $case): string => $case->value, DefineForm::cases()));
    }
}
