<?php

declare(strict_types=1);

namespace Tests\Unit\Generation\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Plan\ProductionPattern;

#[CoversClass(ProductionPattern::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Generation\Derivation\CompletionCosts::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFaker\Generation\Derivation\DerivationNode::class)]
final class ProductionPatternTest extends TestCase
{
    public function testContainingRequiresEveryNamedSymbol(): void
    {
        $pattern = ProductionPattern::containing('FOREIGN', 'KEY');

        self::assertTrue($pattern->matches(['CONSTRAINT', 'FOREIGN', 'KEY', 'REFERENCES']));
        self::assertFalse($pattern->matches(['CONSTRAINT', 'FOREIGN', 'REFERENCES']));
    }

    public function testExactlyMatchesOrderAndCardinality(): void
    {
        $pattern = ProductionPattern::exactly(first: 'CONSTRAINT', second: 'name');

        self::assertTrue($pattern->matches(['CONSTRAINT', 'name']));
        self::assertFalse($pattern->matches(['name', 'CONSTRAINT']));
        self::assertFalse($pattern->matches(['CONSTRAINT', 'name', 'FOREIGN']));
    }

    public function testExactlyCanSelectAnEmptyProduction(): void
    {
        $pattern = ProductionPattern::exactly();

        self::assertTrue($pattern->matches([]));
        self::assertFalse($pattern->matches(['COMMA']));
    }

    public function testNonEmptyRejectsOnlyAnEmptyProduction(): void
    {
        $pattern = ProductionPattern::nonEmpty();

        self::assertTrue($pattern->matches(['name']));
        self::assertFalse($pattern->matches([]));
    }

    public function testMatchesAcceptsAnAlternativeContainingEveryNamedSymbol(): void
    {
        self::assertTrue(ProductionPattern::containing('FOREIGN', 'KEY')->matches(['CONSTRAINT', 'FOREIGN', 'KEY']));
    }

    public function testMatchesRejectsAnAlternativeMissingANamedSymbol(): void
    {
        self::assertFalse(ProductionPattern::containing('FOREIGN', 'KEY')->matches(['FOREIGN']));
    }

    public function testMatchesAcceptsOnlyTheAlternativeWrittenExactly(): void
    {
        self::assertTrue(ProductionPattern::exactly('cmdlist', 'ecmd')->matches(['cmdlist', 'ecmd']));
        self::assertFalse(ProductionPattern::exactly('cmdlist', 'ecmd')->matches(['ecmd', 'cmdlist']));
    }

    public function testMatchesRefusesOnlyTheEmptyAlternative(): void
    {
        self::assertTrue(ProductionPattern::nonEmpty()->matches(['ecmd']));
        self::assertFalse(ProductionPattern::nonEmpty()->matches([]));
    }
    public function testAtSelectsAnOrdinalIndependentlyOfItsSymbols(): void
    {
        $pattern = ProductionPattern::at(2);
        self::assertTrue($pattern->matches(['T'], 2));
        self::assertFalse($pattern->matches(['T'], 1));
        self::assertFalse($pattern->matches(['T']));
    }
    public function testAllOfBooleanPatternsRetainEveryAllowedAlternative(): void
    {
        $pattern = ProductionPattern::allOf(
            ProductionPattern::anyOf(ProductionPattern::containing('VALUES'), ProductionPattern::containing('SELECT')),
            ProductionPattern::excluding(ProductionPattern::containing('WITH')),
        );
        self::assertTrue($pattern->matches(['VALUES', 'row']));
        self::assertTrue($pattern->matches(['SELECT', 'expr']));
        self::assertFalse($pattern->matches(['WITH', 'SELECT', 'expr']));
        self::assertFalse($pattern->matches(['DELETE', 'table']));
    }


    public function testAnyOfAcceptsEitherFormWithoutAcceptingUnrelatedForms(): void
    {
        $pattern = ProductionPattern::anyOf(ProductionPattern::containing('VALUES'), ProductionPattern::containing('SELECT'));
        self::assertTrue($pattern->matches(['VALUES', 'row']));
        self::assertTrue($pattern->matches(['SELECT', 'expr']));
        self::assertFalse($pattern->matches(['UPDATE', 'table']));
    }

    public function testExcludingCanExcludeAnOrdinalOrAnEmptyAlternative(): void
    {
        $pattern = ProductionPattern::excluding(ProductionPattern::at(1));
        self::assertTrue($pattern->matches(['ID'], 0));
        self::assertFalse($pattern->matches(['ID'], 1));
        self::assertFalse(ProductionPattern::excluding(ProductionPattern::exactly())->matches([]));
    }


    public function testAnyOfKeepsTheThirdAlternativeAvailable(): void
    {
        $pattern = ProductionPattern::anyOf(
            ProductionPattern::containing('INSERT'),
            ProductionPattern::containing('UPDATE'),
            ProductionPattern::containing('DELETE'),
        );
        self::assertTrue($pattern->matches(['DELETE', 'table']));
        self::assertFalse($pattern->matches(['SELECT', 'table']));
    }

    public function testAllOfEnforcesEveryCondition(): void
    {
        $pattern = ProductionPattern::allOf(
            ProductionPattern::nonEmpty(),
            ProductionPattern::containing('INSERT'),
            ProductionPattern::excluding(ProductionPattern::containing('WITH')),
        );
        self::assertFalse($pattern->matches(['WITH', 'INSERT']));
        self::assertTrue($pattern->matches(['INSERT']));
    }

}
