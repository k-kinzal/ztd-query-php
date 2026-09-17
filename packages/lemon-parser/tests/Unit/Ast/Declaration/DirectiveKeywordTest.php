<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Declaration;

use LemonParser\Ast\Declaration\DirectiveKeyword;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(DirectiveKeyword::class)]
#[Small]
final class DirectiveKeywordTest extends TestCase
{
    public function testCases(): void
    {
        self::assertSame(['name', 'include', 'code', 'token_destructor', 'default_destructor', 'token_prefix', 'syntax_error', 'parse_accept', 'parse_failure', 'stack_overflow', 'extra_argument', 'extra_context', 'token_type', 'default_type', 'realloc', 'free', 'stack_size', 'start_symbol'], array_map(static fn (DirectiveKeyword $case): string => $case->value, DirectiveKeyword::cases()));
    }
}
