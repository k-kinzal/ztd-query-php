<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use BisonParser\Ast\Declaration\ParamKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ParamKind::class)]
#[Small]
final class ParamKindTest extends TestCase
{
    public function testCases(): void
    {
        self::assertSame(['param', 'lex-param', 'parse-param'], array_map(static fn (ParamKind $case): string => $case->value, ParamKind::cases()));
    }
}
