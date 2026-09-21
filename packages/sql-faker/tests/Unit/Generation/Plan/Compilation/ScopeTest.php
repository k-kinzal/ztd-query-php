<?php

declare(strict_types=1);

namespace Tests\Unit\Generation\Plan\Compilation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Plan\Compilation\Scope;
use SqlFaker\Generation\Plan\LexemeConstraint;
use SqlFaker\Generation\Plan\RulePlan;

#[CoversClass(Scope::class)]
#[UsesClass(RulePlan::class)]
#[UsesClass(LexemeConstraint::class)]
final class ScopeTest extends TestCase
{
    public function testEnterNearerScopesOverrideDefaultsWithoutLeakingToSiblings(): void
    {
        $outer = new Scope([], ['ID' => LexemeConstraint::oneOf('outer')]);
        $inner = $outer->enter(RulePlan::any()->withLexeme('ID', LexemeConstraint::oneOf('inner')));
        self::assertTrue($inner->lexemes['ID']->accepts('inner'));
        self::assertFalse($inner->lexemes['ID']->accepts('outer'));
        self::assertTrue($outer->lexemes['ID']->accepts('outer'));
    }
}
