<?php

declare(strict_types=1);

namespace Tests\Unit\Generation\Plan\Compilation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Exception\LexicalException;
use SqlFaker\Generation\Plan\Compilation\ScopedGeneration;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\LexemeConstraint;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;

#[CoversClass(ScopedGeneration::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(LexemeConstraint::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class ScopedGenerationTest extends TestCase
{
    public function testLexicalPlanRewrittenAndSiblingTokensKeepTheirOwnDomains(): void
    {
        $sequence = new TerminalSequence([
            new TerminalOccurrence('ID', 1, [0, 10]),
            new TerminalOccurrence('ID', -1, [0, 20], rewrite: 'inserted'),
            new TerminalOccurrence('ID', 3, [0, 30]),
        ]);
        $scope = new ScopedGeneration($sequence, [10 => ['ID' => LexemeConstraint::oneOf('target')], 20 => ['ID' => LexemeConstraint::oneOf('source')]]);
        $plan = $scope->lexicalPlan($sequence, GenerationPlan::all(), static fn (int $count): int => 0);
        self::assertSame('target', $plan->lexemeAt('ID', 0));
        self::assertSame('source', $plan->lexemeAt('ID', 1));
        self::assertNull($plan->lexemeAt('ID', 2));
    }

    public function testLexicalPlanExplicitLexemesCannotSilentlyOverrideScopedConditions(): void
    {
        $sequence = new TerminalSequence([new TerminalOccurrence('ID', 1, [0])]);
        $scope = new ScopedGeneration($sequence, [0 => ['ID' => LexemeConstraint::oneOf('target')]]);
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Explicit lexeme contradicts the plan for ID.');
        $scope->lexicalPlan($sequence, GenerationPlan::all()->withLexemes(['ID' => ['different']]), static fn (int $count): int => 0);
    }

    public function testConstraintResolvesNearestAncestorWithoutAffectingOtherTokens(): void
    {
        $outer = LexemeConstraint::oneOf('outer');
        $inner = LexemeConstraint::oneOf('inner');
        $scope = new ScopedGeneration(new TerminalSequence([]), [0 => ['ID' => $outer], 10 => ['ID' => $inner]]);
        self::assertSame($inner, $scope->constraint(new TerminalOccurrence('ID', 1, [0, 10, 20])));
        self::assertSame($outer, $scope->constraint(new TerminalOccurrence('ID', 2, [0, 30])));
        self::assertNull($scope->constraint(new TerminalOccurrence('STRING', 3, [0, 10])));
    }

    public function testLexicalPlanPreservesLegacySpellingsWhenARewriterRenamesTokens(): void
    {
        $sequence = new TerminalSequence([new TerminalOccurrence('RENAMED', 1, [0])], original: [new TerminalOccurrence('ID', 1, [0])]);
        $scope = new ScopedGeneration($sequence, [0 => ['RENAMED' => LexemeConstraint::oneOf('users', 'orders')]]);
        $plan = $scope->lexicalPlan($sequence, GenerationPlan::all()->withLexemes(['ID' => ['orders']]), static fn (int $count): int => 0);
        self::assertSame('orders', $plan->lexemeAt('RENAMED', 0));
    }


    public function testLexicalPlanMatchesRenamedOccurrencesByIdentityAndHonorsFinalTokenOverrides(): void
    {
        $sequence = new TerminalSequence([
            new TerminalOccurrence('RENAMED', 2, [0]),
            new TerminalOccurrence('OVERRIDDEN', 1, [0]),
        ], original: [new TerminalOccurrence('ID', 1, [0]), new TerminalOccurrence('ID', 2, [0])]);
        $scope = new ScopedGeneration($sequence);
        $plan = $scope->lexicalPlan($sequence, GenerationPlan::all()->withLexemes([
            'ID' => ['first', 'second'], 'OVERRIDDEN' => ['final'],
        ]), static fn (int $count): int => 0);
        self::assertSame('second', $plan->lexemeAt('RENAMED', 0));
        self::assertSame('final', $plan->lexemeAt('OVERRIDDEN', 0));
    }

}
