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

    public function testEnterOverridesOnlyDeclaredRolesAndCanonicalizesIndependentDeclarationOrder(): void
    {
        $defaultName = RulePlan::any();
        $targetName = RulePlan::any()->withLexeme('ID', LexemeConstraint::oneOf('users'));
        $value = RulePlan::any()->withLexeme('INTEGER', LexemeConstraint::oneOf('42'));
        $identifier = LexemeConstraint::oneOf('outer');
        $literal = LexemeConstraint::oneOf("'Alice'");
        $first = (new Scope(['value' => $value, 'name' => $defaultName], ['STRING' => $literal, 'ID' => $identifier]))
            ->enter(RulePlan::any()->withRule('name', $targetName)->withLexeme('ID', LexemeConstraint::oneOf('inner')));
        $second = (new Scope(['name' => $defaultName, 'value' => $value], ['ID' => $identifier, 'STRING' => $literal]))
            ->enter(RulePlan::any()->withLexeme('ID', LexemeConstraint::oneOf('inner'))->withRule('name', $targetName));
        self::assertSame(['name' => $targetName, 'value' => $value], $first->rules);
        self::assertSame($literal, $first->lexemes['STRING']);
        self::assertSame(serialize($first), serialize($second));
    }

}
